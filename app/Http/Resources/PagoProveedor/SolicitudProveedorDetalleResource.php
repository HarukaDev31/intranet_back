<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorDatoProveedor;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Services\PagoProveedor\PagoProveedorAprobacionService;
use App\Services\PagoProveedor\PagoProveedorConfirmacionService;
use App\Services\PagoProveedor\PagoProveedorEtapa1Service;
use App\Traits\FileTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudProveedorDetalleResource extends JsonResource
{
    use FileTrait;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PagoProveedorSolicitudProveedor $provider */
        $provider = $this->resource;
        $etapa1 = app(PagoProveedorEtapa1Service::class);
        $aprobacion = app(PagoProveedorAprobacionService::class);
        $cancelled = $provider->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED;
        $collecting = $provider->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT;
        $required = $cancelled ? 0.0 : $etapa1->requiredAmount($provider);
        $balance = $cancelled ? 0.0 : ($collecting ? $aprobacion->outstanding($provider) : $etapa1->balance($provider));
        $paid = $cancelled ? 0.0 : round($required - $balance, 2);
        $data = $provider->datos;
        $title = $provider->supplier_name
            ? $provider->code_supplier . ' · ' . $provider->supplier_name
            : ($provider->code_supplier ?: 'Proveedor ' . $provider->position);

        return [
            'id' => $provider->id,
            'orden' => $provider->position,
            'titulo' => $title,
            'code_supplier' => $provider->code_supplier,
            'consolidated_provider_id' => $provider->consolidatedProvider !== null ? $provider->consolidatedProvider->id : $provider->consolidated_provider_id,
            'nombre_proveedor' => $provider->supplier_name,
            'producto' => $provider->product,
            'moneda' => $provider->currency,
            'tipo_pago' => $provider->payment_type,
            'valor_carga_usd' => $provider->cargo_value_usd,
            'modalidad_abono' => $provider->client_payment_mode,
            'modalidad_pago' => $provider->supplier_payment_mode,
            'cuota_actual' => (int) $provider->current_installment,
            'cuota_carga' => isset($provider->summary_json['cuotas'][max(0, ((int) $provider->current_installment) - 1)]['carga'])
                ? (float) $provider->summary_json['cuotas'][max(0, ((int) $provider->current_installment) - 1)]['carga']
                : null,
            'bancaria_monto' => $provider->bank_fee_amount,
            'administrativa_monto' => $provider->admin_fee_amount,
            'total_monto' => $provider->total_amount,
            'etiqueta_bancaria' => $provider->summary_json['etiqueta_bancaria'] ?? null,
            'importe_requerido' => $required,
            'importe_abonado' => $paid,
            'saldo' => $cancelled ? 0 : $balance,
            'proforma_nombre' => $provider->proforma_name,
            'proforma_url' => $provider->proforma_path ? $this->cdnStorageUrl($provider->proforma_path) : null,
            'etapa' => $provider->stage,
            'etapa_nombre' => self::stageLabel((int) $provider->stage),
            'estado' => $provider->status,
            'estado_nombre' => $cancelled
                ? 'Anulado'
                : ($provider->status === PagoProveedorSolicitudProveedor::STATUS_COMPLETED
                    ? 'Completado'
                    : ($provider->status === PagoProveedorSolicitudProveedor::STATUS_OBSERVED
                        ? 'Observado'
                        : ($collecting ? 'Por cobrar' : 'En proceso'))),
            'observacion_china' => $provider->china_observation_reason ? [
                'reason' => $provider->china_observation_reason,
                'label' => app(\App\Services\PagoProveedor\PagoProveedorYuanService::class)->reasonLabel((string) $provider->china_observation_reason),
                'detail' => $provider->china_observation_detail,
                'until' => optional($provider->china_observe_until)->toIso8601String(),
                'until_text' => $provider->china_observe_until
                    ? ($provider->china_observe_until->isPast()
                        ? 'plazo vencido'
                        : \App\Services\PagoProveedor\PagoProveedorPlazo::remainingText($provider->china_observe_until))
                    : null,
                'overdue' => $provider->china_observe_until ? $provider->china_observe_until->isPast() : false,
            ] : null,
            'observacion_ventas' => $provider->sales_observation ? [
                'motivo' => $provider->sales_observation,
                'fecha' => optional($provider->sales_observed_at)->toIso8601String(),
            ] : null,
            'motivo_anulacion' => $provider->cancellation_reason,
            'progreso' => $this->progress($provider),
            'documento_balance' => $this->documentoBalance($provider),
            'puede_levantar' => $provider->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_REGISTRATION_ERROR,
            'puede_registrar_diferencia' => !$cancelled && $collecting && $balance > PagoProveedorSolicitudProveedor::MONEY_EPSILON,
            'codigo_solicitud' => $provider->request_code,
            'puede_editar_etapa1' => (int) $provider->stage === PagoProveedorSolicitudProveedor::STAGE_DATA && !$cancelled,
            'datos' => $data ? [
                'estado' => $data->status,
                'origen' => $data->source,
                'vendedor_nombre' => $data->seller_name,
                'wechat' => $data->wechat,
                'correccion_solicitada' => $data->correction_requested,
                'beneficiario_nombre' => $data->beneficiary_name,
                'beneficiario_cuenta' => $data->beneficiary_account,
                'beneficiario_direccion' => $data->beneficiary_address,
                'beneficiario_pais' => $data->beneficiary_country,
                'beneficiario_ciudad' => $data->beneficiary_city,
                'banco_tipo_codigo' => $data->bank_code_type,
                'banco_codigo' => $data->bank_code,
                'banco_nombre' => $data->bank_name,
                'banco_direccion' => $data->bank_address,
                'banco_pais' => $data->bank_country,
                'banco_ciudad' => $data->bank_city,
                'usa_intermediario' => (bool) $data->uses_intermediary,
                'inter_tipo_codigo' => $data->intermediary_code_type,
                'inter_codigo' => $data->intermediary_code,
                'inter_nombre' => $data->intermediary_name,
                'inter_direccion' => $data->intermediary_address,
                'inter_pais' => $data->intermediary_country,
                'inter_ciudad' => $data->intermediary_city,
            ] : [
                'estado' => PagoProveedorDatoProveedor::STATUS_PENDING,
                'origen' => null,
                'vendedor_nombre' => null,
                'wechat' => null,
                'correccion_solicitada' => false,
                'beneficiario_nombre' => null,
                'beneficiario_cuenta' => null,
                'beneficiario_direccion' => null,
                'beneficiario_pais' => null,
                'beneficiario_ciudad' => null,
                'banco_tipo_codigo' => null,
                'banco_codigo' => null,
                'banco_nombre' => null,
                'banco_direccion' => null,
                'banco_pais' => null,
                'banco_ciudad' => null,
                'usa_intermediario' => false,
                'inter_tipo_codigo' => null,
                'inter_codigo' => null,
                'inter_nombre' => null,
                'inter_direccion' => null,
                'inter_pais' => null,
                'inter_ciudad' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function progress(PagoProveedorSolicitudProveedor $provider): array
    {
        $progress = app(PagoProveedorConfirmacionService::class)->present($provider);
        foreach ($progress['verification']['deposits'] as $index => $deposit) {
            $path = $deposit['voucher_path'] ?? null;
            $progress['verification']['deposits'][$index]['voucher_url'] = is_string($path) && $path !== '' ? $this->cdnStorageUrl($path) : null;
            unset($progress['verification']['deposits'][$index]['voucher_path']);
        }
        foreach ($progress['verification']['surpluses'] as $index => $surplus) {
            $path = $surplus['voucher_path'] ?? null;
            $progress['verification']['surpluses'][$index]['voucher_url'] = is_string($path) && $path !== '' ? $this->cdnStorageUrl($path) : null;
            unset($progress['verification']['surpluses'][$index]['voucher_path']);
        }
        foreach ($progress['executions'] as $index => $execution) {
            $path = $execution['receipt_path'] ?? null;
            $progress['executions'][$index]['receipt_url'] = is_string($path) && $path !== '' ? $this->cdnStorageUrl($path) : null;
            unset($progress['executions'][$index]['receipt_path']);
        }

        return $progress;
    }

    /**
     * Estado del balance del proveedor sin generarlo (marca si se regenerará).
     *
     * @return array<string, mixed>|null
     */
    private function documentoBalance(PagoProveedorSolicitudProveedor $provider): ?array
    {
        return app(\App\Services\PagoProveedor\PagoProveedorPdfService::class)->estadoBalance($provider);
    }

    public static function stageLabel(int $stage): string
    {
        return [
            PagoProveedorSolicitudProveedor::STAGE_DATA => 'Solicitud y datos de pago',
            PagoProveedorSolicitudProveedor::STAGE_VALIDATION => 'Validación de pago',
            PagoProveedorSolicitudProveedor::STAGE_EXECUTION => 'Ejecución de pago',
            PagoProveedorSolicitudProveedor::STAGE_CONFIRMATION => 'Confirmación',
        ][$stage] ?? 'Solicitud y datos de pago';
    }
}
