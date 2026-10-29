<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorSolicitud;
use App\Traits\FileTrait;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PagoProveedorSolicitudService
{
    use FileTrait;

    public function __construct(
        private readonly PagoProveedorCotizacionService $cotizaciones,
        private readonly PagoProveedorCotizacionPresenter $presenter,
        private readonly PagoProveedorConfiguracionService $configuracion,
        private readonly PagoProveedorHistorialService $historial,
    ) {
    }

    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = PagoProveedorSolicitud::query()->with(['proveedores', 'cotizacion'])->orderByDesc('id');

        if (!empty($filtros['search'])) {
            $texto = '%' . trim((string) $filtros['search']) . '%';
            $query->where(function ($q) use ($texto) {
                $q->where('cliente_nombre', 'like', $texto)
                    ->orWhere('cod_cotizacion_cc', 'like', $texto)
                    ->orWhere('consolidado', 'like', $texto)
                    ->orWhere('numero_orden', 'like', $texto);
            });
        }

        $pagina = $query->paginate((int) ($filtros['per_page'] ?? 15));
        $pagina->getCollection()->transform(fn (PagoProveedorSolicitud $solicitud) => $this->resumen($solicitud));

        return $pagina;
    }

    /**
     * @return array<string, mixed>
     */
    public function ver(int $id): array
    {
        $solicitud = PagoProveedorSolicitud::query()->with(['proveedores', 'cotizacion'])->find($id);
        if (!$solicitud) {
            throw new PagoProveedorException('La solicitud no existe.', 404);
        }

        return $this->detalle($solicitud);
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function crearDesdeCotizacion(int $cotizacionId, array $actor): array
    {
        return DB::transaction(function () use ($cotizacionId, $actor) {
            $cotizacion = $this->cotizaciones->encontrar($cotizacionId, true);
            $this->cotizaciones->exigirPendiente($cotizacion);

            if ($cotizacion->solicitud()->exists()) {
                throw new PagoProveedorException('Esta cotización ya tiene una solicitud.');
            }

            $presentado = $this->presenter->cotizacion($cotizacion, $this->configuracion->tarifa());
            if ($presentado['vencida']) {
                throw new PagoProveedorException('La cotización está vencida. Renuévala antes de crear la solicitud.');
            }
            if (!$presentado['puede_crear_solicitud']) {
                throw new PagoProveedorException(implode(' ', $presentado['faltantes']));
            }

            $numero = ((int) PagoProveedorSolicitud::query()->lockForUpdate()->max('numero_orden')) + 1;
            $solicitud = PagoProveedorSolicitud::query()->create([
                'numero_orden' => $numero,
                'cotizacion_id' => $cotizacion->id,
                'cod_cotizacion_cc' => $cotizacion->cod_cotizacion_cc,
                'id_cotizacion_cc' => $cotizacion->id_cotizacion_cc,
                'tipo_cambio' => $cotizacion->tipo_cambio,
                'tipo_cambio_confirmado' => true,
                'cliente_nombre' => $cotizacion->cliente_nombre,
                'cliente_documento' => $cotizacion->cliente_documento,
                'cliente_whatsapp' => $cotizacion->cliente_whatsapp,
                'cliente_correo' => $cotizacion->cliente_correo,
                'consolidado' => $cotizacion->consolidado,
                'usuario_id' => $actor['id'],
            ]);

            $porOrden = [];
            foreach ($presentado['resumen']['proveedores'] as $linea) {
                $porOrden[$linea['orden']] = $linea;
            }

            foreach ($cotizacion->proveedores as $proveedor) {
                $linea = $porOrden[$proveedor->orden] ?? [];
                $solicitud->proveedores()->create([
                    'orden' => $proveedor->orden,
                    'code_supplier' => $proveedor->code_supplier,
                    'id_proveedor_cc' => $proveedor->id_proveedor_cc,
                    'nombre_proveedor' => $proveedor->nombre_proveedor,
                    'producto' => $proveedor->producto,
                    'moneda' => $linea['moneda'] ?? $proveedor->moneda,
                    'tipo_pago' => $proveedor->tipo_pago,
                    'valor_carga_usd' => $proveedor->valor_carga_usd,
                    'modalidad_abono' => $linea['modalidad_abono'] ?? $proveedor->modalidad_abono,
                    'modalidad_pago' => $linea['modalidad_pago'] ?? $proveedor->modalidad_pago,
                    'porcentaje_1' => $proveedor->porcentaje_1,
                    'porcentaje_2' => $proveedor->porcentaje_2,
                    'fecha_cuota_2' => $proveedor->fecha_cuota_2,
                    'comision_bancaria' => $proveedor->comision_bancaria,
                    'comision_administrativa' => $proveedor->comision_administrativa,
                    'bancaria_monto' => $linea['bancaria'] ?? 0,
                    'administrativa_monto' => $linea['administrativa'] ?? 0,
                    'total_monto' => $linea['total'] ?? 0,
                    'bancaria_incluida_en_orden' => $linea['bancaria_incluida_en'] ?? null,
                    'resumen_json' => $linea,
                    'proforma_path' => $proveedor->proforma_path,
                    'proforma_nombre' => $proveedor->proforma_nombre,
                    'etapa' => 1,
                    'estado' => 'en_proceso',
                    'cuota_actual' => 1,
                    'pago_actual' => 1,
                ]);
            }

            $cotizacion->estado = 'confirmada';
            $cotizacion->save();
            $this->historial->registrar('cotizacion', $cotizacion->id, 'Confirmada', $actor, 'Solicitud N.° ' . $numero);
            $this->historial->registrar('solicitud', $solicitud->id, 'Creada', $actor, 'Proveedores en etapa 1.');

            return $this->detalle($solicitud->load('proveedores', 'cotizacion'));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function resumen(PagoProveedorSolicitud $solicitud): array
    {
        return [
            'id' => $solicitud->id,
            'numero_orden' => $solicitud->numero_orden,
            'cotizacion_id' => $solicitud->cotizacion_id,
            'cotizacion_codigo' => $solicitud->cotizacion?->codigo,
            'cod_cotizacion_cc' => $solicitud->cod_cotizacion_cc,
            'cliente_nombre' => $solicitud->cliente_nombre,
            'consolidado' => $solicitud->consolidado,
            'proveedores_cantidad' => $solicitud->proveedores->count(),
            'creado_en' => optional($solicitud->created_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detalle(PagoProveedorSolicitud $solicitud): array
    {
        $resumen = $this->resumen($solicitud);
        $resumen['tipo_cambio'] = $solicitud->tipo_cambio;
        $resumen['cliente_documento'] = $solicitud->cliente_documento;
        $resumen['cliente_whatsapp'] = $solicitud->cliente_whatsapp;
        $resumen['cliente_correo'] = $solicitud->cliente_correo;
        $resumen['proveedores'] = $solicitud->proveedores->map(function ($proveedor) {
            $titulo = $proveedor->nombre_proveedor
                ? $proveedor->code_supplier . ' · ' . $proveedor->nombre_proveedor
                : $proveedor->code_supplier;

            return [
                'id' => $proveedor->id,
                'orden' => $proveedor->orden,
                'titulo' => $titulo,
                'code_supplier' => $proveedor->code_supplier,
                'nombre_proveedor' => $proveedor->nombre_proveedor,
                'producto' => $proveedor->producto,
                'moneda' => $proveedor->moneda,
                'tipo_pago' => $proveedor->tipo_pago,
                'valor_carga_usd' => $proveedor->valor_carga_usd,
                'modalidad_abono' => $proveedor->modalidad_abono,
                'modalidad_pago' => $proveedor->modalidad_pago,
                'bancaria_monto' => $proveedor->bancaria_monto,
                'administrativa_monto' => $proveedor->administrativa_monto,
                'total_monto' => $proveedor->total_monto,
                'etiqueta_bancaria' => $proveedor->resumen_json['etiqueta_bancaria'] ?? null,
                'proforma_nombre' => $proveedor->proforma_nombre,
                'proforma_url' => $proveedor->proforma_path ? $this->cdnStorageUrl($proveedor->proforma_path) : null,
                'etapa' => $proveedor->etapa,
                'etapa_nombre' => 'Solicitud y datos de pago',
                'estado' => $proveedor->estado,
                'estado_nombre' => 'En proceso',
            ];
        })->values()->all();

        return $resumen;
    }
}
