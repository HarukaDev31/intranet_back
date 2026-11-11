<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Models\PagoProveedor\PagoProveedorHistorial;
use App\Models\PagoProveedor\PagoProveedorSolicitud;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PagoProveedorSolicitudService
{
    public function __construct(
        private readonly PagoProveedorCotizacionService $cotizaciones,
        private readonly PagoProveedorCotizacionPresenter $presenter,
        private readonly PagoProveedorConfiguracionService $configuracion,
        private readonly PagoProveedorHistorialService $historial,
    ) {
    }

    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = PagoProveedorSolicitud::query()->with(['proveedores.datos', 'proveedores.consolidatedProvider', 'cotizacion'])->orderByDesc('id');

        // China's manual requests belong to other partners and never reach Sales (RN-97).
        $query->where(function ($own) {
            $own->whereNull('origin')->orWhere('origin', '!=', PagoProveedorSolicitud::ORIGIN_MANUAL);
        });

        if (!empty($filtros['search'])) {
            $texto = '%' . trim((string) $filtros['search']) . '%';
            $query->where(function ($q) use ($texto) {
                $q->where('client_name', 'like', $texto)
                    ->orWhere('consolidated_quote_code', 'like', $texto)
                    ->orWhere('consolidation', 'like', $texto)
                    ->orWhere('order_number', 'like', $texto)
                    ->orWhereHas('proveedores', function ($proveedores) use ($texto) {
                        $proveedores->where('request_code', 'like', $texto)
                            ->orWhere('code_supplier', 'like', $texto);
                    });
            });
        }

        $consolidado = trim((string) ($filtros['consolidado'] ?? ''));
        if ($consolidado !== '' && strtolower($consolidado) !== 'todos') {
            $query->where('consolidation', $consolidado);
        }

        // Moneda, etapa y estado son de cada proveedor: la orden solo muestra los proveedores que cumplen el filtro.
        $tipoPago = strtoupper(trim((string) ($filtros['tipo_pago'] ?? '')));
        $etapa = trim((string) ($filtros['etapa'] ?? ''));
        $estado = trim((string) ($filtros['estado'] ?? ''));
        $filtraMoneda = in_array($tipoPago, ['RMB', 'USD'], true);
        $filtraEtapa = $etapa !== '' && strtolower($etapa) !== 'todos';
        $filtraEstado = $estado !== '' && strtolower($estado) !== 'todos';
        if ($filtraMoneda || $filtraEtapa || $filtraEstado) {
            $delProveedor = function ($proveedores) use ($filtraMoneda, $tipoPago, $filtraEtapa, $etapa, $filtraEstado, $estado) {
                if ($filtraMoneda) {
                    $proveedores->where('currency', $tipoPago);
                }
                if ($filtraEtapa) {
                    $proveedores->where('stage', (int) $etapa);
                }
                if ($filtraEstado) {
                    $proveedores->where('status', $estado);
                }
            };
            $query->whereHas('proveedores', $delProveedor)
                ->with(['proveedores' => $delProveedor, 'proveedores.datos', 'proveedores.consolidatedProvider']);
        }

        return $query->paginate((int) ($filtros['per_page'] ?? 15));
    }

    public function ver(int $id): PagoProveedorSolicitud
    {
        $solicitud = PagoProveedorSolicitud::query()->with(['proveedores.datos', 'proveedores.consolidatedProvider', 'cotizacion'])->find($id);
        if (!$solicitud) {
            throw new PagoProveedorException('La solicitud no existe.', 404);
        }

        return $solicitud;
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function crearDesdeCotizacion(int $cotizacionId, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($cotizacionId, $actor) {
            $cotizacion = $this->cotizaciones->encontrar($cotizacionId, true);
            $this->cotizaciones->exigirPendiente($cotizacion);

            if ($cotizacion->solicitud()->exists()) {
                throw new PagoProveedorException('Esta cotización ya tiene una solicitud.');
            }

            $presentado = $this->presenter->cotizacion($cotizacion, $this->configuracion->tariff());
            if ($presentado['vencida']) {
                throw new PagoProveedorException('La cotización está vencida. Renuévala antes de crear la solicitud.');
            }
            if (!$presentado['puede_crear_solicitud']) {
                throw new PagoProveedorException(implode(' ', $presentado['faltantes']));
            }

            $numero = ((int) PagoProveedorSolicitud::query()->lockForUpdate()->max('order_number')) + 1;
            $solicitud = PagoProveedorSolicitud::query()->create([
                'order_number' => $numero,
                'quote_id' => $cotizacion->id,
                'consolidated_quote_code' => $cotizacion->consolidated_quote_code,
                'consolidated_quote_id' => $cotizacion->consolidated_quote_id,
                'exchange_rate' => $cotizacion->exchange_rate,
                'exchange_rate_confirmed' => true,
                'client_name' => $cotizacion->client_name,
                'client_document' => $cotizacion->client_document,
                'client_whatsapp' => $cotizacion->client_whatsapp,
                'client_email' => $cotizacion->client_email,
                'consolidation' => $cotizacion->consolidation,
                'user_id' => $actor['id'],
            ]);

            $porOrden = [];
            foreach ($presentado['resumen']['proveedores'] as $linea) {
                $porOrden[$linea['orden']] = $linea;
            }

            foreach ($cotizacion->proveedores as $proveedor) {
                $linea = $porOrden[$proveedor->position] ?? [];
                $solicitud->proveedores()->create([
                    'position' => $proveedor->position,
                    'code_supplier' => $proveedor->code_supplier,
                    'consolidated_provider_id' => $proveedor->consolidated_provider_id,
                    'supplier_name' => $proveedor->supplier_name,
                    'product' => $proveedor->product,
                    'currency' => $linea['moneda'] ?? $proveedor->currency,
                    'payment_type' => $proveedor->payment_type,
                    'cargo_value_usd' => $proveedor->cargo_value_usd,
                    'client_payment_mode' => $linea['modalidad_abono'] ?? $proveedor->client_payment_mode,
                    'supplier_payment_mode' => $linea['modalidad_pago'] ?? $proveedor->supplier_payment_mode,
                    'first_share_percent' => $proveedor->first_share_percent,
                    'second_share_percent' => $proveedor->second_share_percent,
                    'second_installment_on' => $proveedor->second_installment_on,
                    'charges_bank_fee' => $proveedor->charges_bank_fee,
                    'charges_admin_fee' => $proveedor->charges_admin_fee,
                    'bank_fee_amount' => $linea['bancaria'] ?? 0,
                    'admin_fee_amount' => $linea['administrativa'] ?? 0,
                    'total_amount' => $linea['total'] ?? 0,
                    'bank_fee_on_position' => $linea['bancaria_incluida_en'] ?? null,
                    'summary_json' => $linea,
                    'proforma_path' => $proveedor->proforma_path,
                    'proforma_name' => $proveedor->proforma_name,
                    'stage' => PagoProveedorSolicitudProveedor::STAGE_DATA,
                    'status' => PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS,
                    'current_installment' => PagoProveedorSolicitudProveedor::FIRST_INSTALLMENT,
                    'current_payment' => PagoProveedorSolicitudProveedor::FIRST_INSTALLMENT,
                ]);
            }

            $cotizacion->status = PagoProveedorCotizacion::STATUS_CONFIRMED;
            $cotizacion->save();
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_QUOTE, $cotizacion->id, 'Confirmada', $actor, 'Solicitud N.° ' . $numero);
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitud->id, 'Creada', $actor, 'Proveedores en etapa 1.');

            return $solicitud->load(['proveedores.datos', 'cotizacion']);
        });
    }
}
