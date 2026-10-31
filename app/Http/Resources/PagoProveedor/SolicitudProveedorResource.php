<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudProveedorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PagoProveedorSolicitudProveedor $provider */
        $provider = $this->resource;
        $task = $provider->salesTask();
        $solicitud = $provider->solicitud;

        return [
            'id' => $provider->id,
            'request_id' => $provider->request_id,
            'order_number' => $solicitud?->order_number,
            'supplier_code' => $provider->code_supplier,
            'consolidated_provider_id' => $provider->consolidatedProvider?->id ?? $provider->consolidated_provider_id,
            'supplier_name' => $provider->supplier_name,
            'product' => $provider->product,
            'currency' => $provider->currency,
            'payment_type' => $provider->payment_type,
            'payment_shape' => $provider->paymentShape(),
            'stage' => (int) $provider->stage,
            'status' => $provider->status,
            'request_code' => $provider->request_code,
            'approval_status' => $provider->approval_status,
            'collect_amount' => $provider->collect_amount,
            'exchange_result' => $provider->exchange_result,
            'second_payment_choice' => $provider->second_payment_choice,
            'sales_task' => $task,
            'owner' => $task ? PagoProveedorSolicitudProveedor::OWNER_SALES : PagoProveedorSolicitudProveedor::OWNER_OTHER,
        ];
    }
}
