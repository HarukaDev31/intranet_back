<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorAbono;
use App\Models\PagoProveedor\PagoProveedorReembolso;
use App\Services\PagoProveedor\PagoProveedorReembolsoService;
use App\Traits\FileTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReembolsoResource extends JsonResource
{
    use FileTrait;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PagoProveedorReembolso $refund */
        $refund = $this->resource;
        $service = app(PagoProveedorReembolsoService::class);

        return [
            'id' => $refund->id,
            'request_id' => $refund->request_id,
            'provider_id' => $refund->request_provider_id,
            'order_number' => $refund->solicitud?->order_number,
            'client_name' => $refund->solicitud?->client_name,
            'consolidation' => $refund->solicitud?->consolidation,
            'supplier_code' => $refund->proveedor?->code_supplier,
            'supplier_name' => $refund->proveedor?->supplier_name,
            'product' => $refund->proveedor?->product,
            'request_code' => $refund->proveedor?->request_code,
            'currency' => $refund->proveedor?->currency,
            'origin' => $refund->origin,
            'origin_label' => match ($refund->origin) {
                PagoProveedorReembolso::ORIGIN_EXCHANGE => 'Diferencia de cambio',
                PagoProveedorReembolso::ORIGIN_PAYMENT => 'Pago 2 anulado',
                default => 'Abono de más',
            },
            'status' => $refund->status,
            'status_label' => match ($refund->status) {
                PagoProveedorReembolso::STATUS_REQUESTED => 'Reembolso solicitado',
                PagoProveedorReembolso::STATUS_APPROVED => 'Reembolso aprobado',
                PagoProveedorReembolso::STATUS_REJECTED => 'Rechazado',
                default => 'Por consultar al cliente',
            },
            'amount' => (float) $refund->amount,
            'approved_amount' => $refund->approved_amount,
            'reason' => $refund->reason,
            'calculation' => $service->explain($refund),
            'calculation_lines' => $service->explanationLines($refund),
            'requested_at' => optional($refund->requested_at)->toIso8601String(),
            'paid_on' => optional($refund->paid_on)->toDateString(),
            'bank' => $refund->bank,
            'bank_label' => $refund->bank === PagoProveedorAbono::BANK_INTERBANK ? 'Interbank' : ($refund->bank === PagoProveedorAbono::BANK_BCP ? 'BCP' : $refund->bank),
            'operation_number' => $refund->operation_number,
            'voucher_name' => $refund->voucher_name,
            'voucher_url' => $refund->voucher_path ? $this->cdnStorageUrl($refund->voucher_path) : null,
            'can_approve' => $refund->status === PagoProveedorReembolso::STATUS_REQUESTED,
            'can_reject' => $refund->status === PagoProveedorReembolso::STATUS_REQUESTED,
        ];
    }
}
