<?php

namespace App\Http\Resources\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Services\PagoProveedor\PagoProveedorComisionService;
use App\Services\PagoProveedor\PagoProveedorConfiguracionService;
use App\Services\PagoProveedor\PagoProveedorCotizacionCcLookup;
use App\Services\PagoProveedor\PagoProveedorVigenciaService;
use App\Services\PagoProveedor\TarifaPagoProveedor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CotizacionResource extends JsonResource
{
    private bool $includeHistory = false;

    private bool $lookupCodes = false;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $codes = null;

    private ?TarifaPagoProveedor $tarifa = null;

    public static function detail(PagoProveedorCotizacion $cotizacion): self
    {
        $resource = new self($cotizacion);
        $resource->includeHistory = true;
        $resource->lookupCodes = true;

        return $resource;
    }

    public function includeHistory(): self
    {
        $this->includeHistory = true;

        return $this;
    }

    /**
     * @param  array<int, array<string, mixed>>  $codes
     */
    public function supplierCodes(array $codes): self
    {
        $this->codes = $codes;
        $this->lookupCodes = false;

        return $this;
    }

    public function usingTarifa(TarifaPagoProveedor $tarifa): self
    {
        $this->tarifa = $tarifa;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PagoProveedorCotizacion $cotizacion */
        $cotizacion = $this->resource;
        $cotizacion->loadMissing(['proveedores.consolidatedProvider', 'solicitud']);

        $vigencia = app(PagoProveedorVigenciaService::class);
        $expired = $cotizacion->status === PagoProveedorCotizacion::STATUS_PENDING && $vigencia->estaVencida($cotizacion->valid_until);
        $calculation = app(PagoProveedorComisionService::class)->calcular(
            $cotizacion->proveedores->map(fn (PagoProveedorCotizacionProveedor $provider) => $this->line($provider))->all(),
            $this->tarifa()
        );
        $byOrder = [];
        foreach ($calculation['proveedores'] as $line) {
            $byOrder[$line['orden']] = $line;
        }
        $complete = $this->isComplete($cotizacion);
        $pending = $cotizacion->status === PagoProveedorCotizacion::STATUS_PENDING;
        $ageDays = $cotizacion->created_at
            ? max(0, (int) $cotizacion->created_at->copy()->startOfDay()->diffInDays(now()->startOfDay()))
            : 0;
        $highlightFrom = max(0, (int) $request->get('resaltar_desde', 0));
        $highlight = $pending && $highlightFrom > 0 && $ageDays >= $highlightFrom;
        $ageText = !$pending
            ? null
            : ($ageDays === 0 ? 'hoy' : ($ageDays === 1 ? 'hace 1 día' : 'hace '.$ageDays.' días'));

        return [
            'id' => $cotizacion->id,
            'codigo' => $cotizacion->code,
            'solicitud_id' => $cotizacion->solicitud?->id,
            'solicitud_numero' => $cotizacion->solicitud?->order_number,
            'estado' => $cotizacion->status,
            'cod_cotizacion_cc' => $cotizacion->consolidated_quote_code,
            'consolidado' => $cotizacion->consolidation,
            'cliente_nombre' => $cotizacion->client_name,
            'cliente_documento' => $cotizacion->client_document,
            'cliente_whatsapp' => $cotizacion->client_whatsapp,
            'cliente_correo' => $cotizacion->client_email,
            'tipo_cambio' => $cotizacion->exchange_rate,
            'tipo_cambio_fecha' => optional($cotizacion->created_at)
                ?->timezone(PagoProveedorVigenciaService::ZONA)
                ?->format('d/m/Y'),
            'tipo_cambio_confirmado' => $this->tipoCambioConfirmado($cotizacion),
            'vigencia_fin' => optional($cotizacion->valid_until)->toIso8601String(),
            'vigencia_texto' => $cotizacion->status === PagoProveedorCotizacion::STATUS_CONFIRMED
                ? 'Confirmada'
                : $vigencia->texto($cotizacion->valid_until),
            'vigencia_hasta' => $cotizacion->status === PagoProveedorCotizacion::STATUS_CONFIRMED
                ? null
                : optional($cotizacion->valid_until)?->timezone(PagoProveedorVigenciaService::ZONA)->format('d/m/Y H:i'),
            'resumen_enviado' => \App\Models\PagoProveedor\PagoProveedorMensaje::query()
                ->where('quote_id', $cotizacion->id)
                ->where('message_type', 'resumen')
                ->exists(),
            'vencida' => $expired,
            'antiguedad_dias' => $pending ? $ageDays : null,
            'antiguedad_texto' => $ageText,
            'resaltar' => $highlight,
            'proveedores_cantidad' => $cotizacion->proveedores->count(),
            'total' => $calculation['total'],
            'total_ahora' => $calculation['total_ahora'],
            'comisiones' => $calculation['comisiones'],
            'puede_editar' => $pending,
            'puede_eliminar' => $pending && !$cotizacion->solicitud,
            'puede_enviar' => $pending && !$expired && $complete['pdf'] && trim((string) $cotizacion->client_whatsapp) !== '',
            'puede_descargar_pdf' => $complete['pdf'] && (!$expired || !$pending),
            'puede_crear_solicitud' => $pending && !$expired && $complete['solicitud'],
            'puede_renovar' => $expired,
            'faltantes_pdf' => $complete['faltantes_pdf'],
            'faltantes' => $complete['faltantes'],
            'codigos_disponibles' => $this->availableCodes($cotizacion),
            'proveedores' => $cotizacion->proveedores->map(
                fn (PagoProveedorCotizacionProveedor $provider) => (new CotizacionProveedorResource($provider))
                    ->withLine($byOrder[$provider->position] ?? null)
                    ->resolve()
            )->values()->all(),
            'resumen' => $calculation,
            'documento' => $this->includeHistory ? $this->documento($cotizacion) : null,
            'creado_en' => optional($cotizacion->created_at)->toIso8601String(),
            'historial' => $this->includeHistory
                ? HistorialResource::collection($cotizacion->historial()->limit(30)->get())->resolve()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function documento(PagoProveedorCotizacion $cotizacion): ?array
    {
        /** @var \App\Services\PagoProveedor\PagoProveedorPdfService $pdf */
        $pdf = app(\App\Services\PagoProveedor\PagoProveedorPdfService::class);

        return $pdf->estadoResumen($cotizacion);
    }

    private function tarifa(): TarifaPagoProveedor
    {
        return $this->tarifa ?? app(PagoProveedorConfiguracionService::class)->tariff();
    }

    /**
     * @return array<string, mixed>
     */
    private function line(PagoProveedorCotizacionProveedor $provider): array
    {
        return [
            'orden' => $provider->position,
            'moneda' => $provider->currency,
            'valor_carga_usd' => $provider->cargo_value_usd,
            'modalidad_abono' => $provider->client_payment_mode,
            'modalidad_pago' => $provider->supplier_payment_mode,
            'porcentaje_1' => $provider->first_share_percent,
            'porcentaje_2' => $provider->second_share_percent,
            'comision_bancaria' => $provider->charges_bank_fee,
            'comision_administrativa' => $provider->charges_admin_fee,
        ];
    }

    /**
     * @return array{pdf: bool, solicitud: bool, faltantes: array<int, string>, faltantes_pdf: array<int, string>}
     */
    private function isComplete(PagoProveedorCotizacion $cotizacion): array
    {
        $missing = [];
        $hayYuanes = $cotizacion->proveedores->contains('currency', PagoProveedorCotizacionProveedor::CURRENCY_RMB);
        if ($hayYuanes && (float) $cotizacion->exchange_rate <= 0) {
            $missing[] = 'Falta el tipo de cambio.';
        }

        foreach ($cotizacion->proveedores as $provider) {
            $n = $provider->position;
            if (!$provider->code_supplier) {
                $missing[] = "Proveedor {$n}: elige el código.";
            }
            if (!trim((string) $provider->product)) {
                $missing[] = "Proveedor {$n}: falta el producto.";
            }
            if ((float) $provider->cargo_value_usd <= 0) {
                $missing[] = "Proveedor {$n}: el valor de carga debe ser mayor a 0.";
            }
            if ($provider->client_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT) {
                $sum = round((float) $provider->first_share_percent + (float) $provider->second_share_percent, 2);
                if ($sum !== (float) PagoProveedorCotizacionProveedor::FULL_SHARE) {
                    $missing[] = "Proveedor {$n}: los porcentajes deben sumar 100.";
                }
            }
        }

        $missingPdf = $missing;
        $missingRequest = $missing;
        // Confirmar el tipo de cambio del día solo se exige para crear la solicitud: el PDF y el
        // WhatsApp siguen disponibles aunque se haya editado la cotización sin reconfirmarlo.
        if ($hayYuanes && (float) $cotizacion->exchange_rate > 0 && !$this->tipoCambioConfirmado($cotizacion)) {
            $missingRequest[] = 'Confirma el tipo de cambio.';
        }
        foreach ($cotizacion->proveedores as $provider) {
            $n = $provider->position;
            if (!$provider->proforma_path) {
                $missingRequest[] = "Proveedor {$n}: falta la proforma.";
            }
            if ($provider->client_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT && !$provider->second_installment_on) {
                $missingRequest[] = "Proveedor {$n}: falta la fecha de la cuota 2.";
            }
        }

        if ($cotizacion->proveedores->isEmpty()) {
            $missingPdf[] = 'Agrega al menos un proveedor.';
            $missingRequest[] = 'Agrega al menos un proveedor.';
        }

        return [
            'pdf' => $missingPdf === [],
            'solicitud' => $missingRequest === [],
            'faltantes_pdf' => $missingPdf,
            'faltantes' => $missingRequest,
        ];
    }

    /**
     * Tipo de cambio del día: la cotización se creó hoy o Ventas lo confirmó hoy (hora de Lima).
     * El flag guardado otro día ya no vale (#1656).
     */
    private function tipoCambioConfirmado(PagoProveedorCotizacion $cotizacion): bool
    {
        return $cotizacion->exchangeRateConfirmedToday();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function availableCodes(PagoProveedorCotizacion $cotizacion): array
    {
        if (is_array($this->codes)) {
            return $this->codes;
        }
        if (!$this->lookupCodes) {
            return [];
        }

        try {
            return app(PagoProveedorCotizacionCcLookup::class)->findByCode($cotizacion->consolidated_quote_code)['proveedores'];
        } catch (PagoProveedorException) {
            return [];
        }
    }
}
