<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

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
        $deadline = $task !== null ? $this->taskDeadline($provider) : null;

        return [
            'id' => $provider->id,
            'request_id' => $provider->request_id,
            'order_number' => $solicitud?->order_number,
            'supplier_code' => $provider->code_supplier,
            'consolidated_provider_id' => $provider->consolidatedProvider !== null ? $provider->consolidatedProvider->id : $provider->consolidated_provider_id,
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
            'second_installment_on' => optional($provider->second_installment_on)->format('Y-m-d'),
            'current_installment' => (int) $provider->current_installment,
            'decision_due_at' => optional($provider->decision_due_at)->toIso8601String(),
            'decision_due_text' => $provider->decision_due_at
                ? ($provider->decision_due_at->isPast()
                    ? 'plazo vencido'
                    : \App\Services\PagoProveedor\PagoProveedorPlazo::remainingText($provider->decision_due_at))
                : null,
            'observe_until' => optional($provider->china_observe_until)->toIso8601String(),
            'observe_text' => $provider->status === PagoProveedorSolicitudProveedor::STATUS_OBSERVED && $provider->china_observe_until
                ? ($provider->china_observe_until->isPast()
                    ? 'plazo vencido'
                    : \App\Services\PagoProveedor\PagoProveedorPlazo::remainingText($provider->china_observe_until))
                : null,
            'observe_label' => $provider->china_observation_reason
                ? app(\App\Services\PagoProveedor\PagoProveedorYuanService::class)->reasonLabel((string) $provider->china_observation_reason)
                : null,
            'sales_task' => $task,
            // Fecha límite de la tarea pendiente de Ventas (#1732: tablero y lista ordenan por ella).
            'deadline_at' => $deadline !== null ? $deadline['at']->toIso8601String() : null,
            'deadline_kind' => $deadline !== null ? $deadline['kind'] : null,
            'owner' => $task ? PagoProveedorSolicitudProveedor::OWNER_SALES : PagoProveedorSolicitudProveedor::OWNER_OTHER,
        ];
    }

    /**
     * Fecha límite de lo que Ventas tiene pendiente: cobro de diferencia, observación de China,
     * plazo para decidir o compromiso de la 2.ª cuota del cliente (en ese orden).
     *
     * @return array{at: Carbon, kind: string}|null
     */
    private function taskDeadline(PagoProveedorSolicitudProveedor $provider): ?array
    {
        if ($provider->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT && $provider->collect_until !== null) {
            return ['at' => $provider->collect_until, 'kind' => 'collect'];
        }
        $observeUntil = $provider->china_observe_until;
        if ($provider->status === PagoProveedorSolicitudProveedor::STATUS_OBSERVED && $observeUntil instanceof Carbon) {
            return ['at' => $observeUntil, 'kind' => 'observation'];
        }
        if ($provider->decision_due_at !== null) {
            return ['at' => $provider->decision_due_at, 'kind' => 'decision'];
        }
        if ($provider->client_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT
            && (int) $provider->current_installment === PagoProveedorSolicitudProveedor::FIRST_INSTALLMENT
            && $provider->second_installment_on !== null) {
            return [
                'at' => Carbon::parse($provider->second_installment_on->format('Y-m-d'), 'America/Lima')->endOfDay(),
                'kind' => 'commitment',
            ];
        }

        return null;
    }
}
