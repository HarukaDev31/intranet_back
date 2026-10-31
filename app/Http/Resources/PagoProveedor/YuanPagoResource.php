<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Services\PagoProveedor\PagoProveedorConfiguracionService;
use App\Services\PagoProveedor\PagoProveedorYuanService;
use App\Traits\FileTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class YuanPagoResource extends JsonResource
{
    use FileTrait;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PagoProveedorSolicitudProveedor $provider */
        $provider = $this->resource;
        $yuan = app(PagoProveedorYuanService::class);
        $quote = $yuan->quote($provider);
        $execution = $provider->ejecuciones->first(
            fn ($row) => (int) $row->installment === (int) $provider->current_installment
                && (int) $row->supplier_payment === (int) $provider->current_payment
        );
        if (!$execution && (int) $provider->stage >= PagoProveedorSolicitudProveedor::STAGE_CONFIRMATION) {
            $execution = $provider->ejecuciones->sortByDesc('id')->first();
        }
        $observed = $provider->status === PagoProveedorSolicitudProveedor::STATUS_OBSERVED
            && $provider->china_observation_reason !== null;
        $status = $observed
            ? 'observado'
            : ($execution && (int) $provider->stage >= PagoProveedorSolicitudProveedor::STAGE_CONFIRMATION
                ? 'pagado'
                : 'pendiente');

        return [
            'provider_id' => $provider->id,
            'request_id' => $provider->request_id,
            'order_number' => $provider->solicitud?->order_number,
            'request_code' => $provider->request_code,
            'supplier_code' => $provider->code_supplier,
            'supplier_name' => $provider->supplier_name,
            'product' => $provider->product,
            'wechat' => $provider->datos?->wechat,
            'seller_name' => $provider->datos?->seller_name,
            'client_name' => $provider->solicitud?->client_name,
            'consolidation' => $provider->solicitud?->consolidation,
            'partner' => $provider->solicitud?->partner ?: 'Pro Business',
            'usd_amount' => $quote['usd'],
            'quoted_rate' => $quote['quoted_rate'],
            'yuan_amount' => $quote['quoted_yuan'],
            'yuan_obtained' => $execution?->yuan_obtained,
            'yuan_paid' => $execution?->yuan_paid,
            'real_exchange_rate' => $execution?->real_exchange_rate,
            'difference_usd' => $execution?->exchange_difference_usd,
            'difference_result' => $execution?->exchange_result,
            'difference_label' => $execution?->exchange_result ? $yuan->resultLabel((string) $execution->exchange_result) : null,
            'status' => $status,
            'status_label' => match ($status) {
                'observado' => 'Observado',
                'pagado' => 'Pagado',
                default => 'Pendiente',
            },
            'proforma_url' => $provider->proforma_path ? $this->cdnStorageUrl($provider->proforma_path) : null,
            'receipt_url' => $execution?->receipt_path ? $this->cdnStorageUrl($execution->receipt_path) : null,
            'current_payment' => (int) $provider->current_payment,
            'supplier_payments' => is_array($provider->summary_json['pagos_proveedor'] ?? null)
                ? count($provider->summary_json['pagos_proveedor'])
                : 1,
            'can_register' => $status === 'pendiente',
            'can_observe' => $status === 'pendiente',
            'observation_reason' => $provider->china_observation_reason,
            'observation_label' => $provider->china_observation_reason
                ? $yuan->reasonLabel((string) $provider->china_observation_reason)
                : null,
            'observation_detail' => $provider->china_observation_detail,
            'observation_until' => optional($provider->china_observe_until)->toIso8601String(),
            'observation_overdue' => $provider->china_observe_until ? $provider->china_observe_until->isPast() : false,
            'tolerance' => (float) app(PagoProveedorConfiguracionService::class)->value(PagoProveedorSolicitudProveedor::SETTING_TOLERANCE, 1),
        ];
    }
}
