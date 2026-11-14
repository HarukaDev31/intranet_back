<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorReembolso;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Services\PagoProveedor\PagoProveedorConfiguracionService;
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
            // #1745: si no le toca a Ventas, quién lo tiene y qué falta para cerrar.
            'waiting_on' => $task === null ? $this->waitingOn($provider) : null,
        ];
    }

    /**
     * Fecha límite de lo que Ventas tiene pendiente: cobro de diferencia, observación de China,
     * plazo para decidir o compromiso de la 2.ª cuota del cliente (en ese orden); si no hay
     * ninguno, el plazo por defecto de las tareas de Ventas.
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

        // #1742: el resto de tareas vence por defecto a las N h (48) desde que empezó:
        // en Solicitud y datos, desde que se creó la solicitud; en las demás, desde el último cambio.
        $since = (int) $provider->stage === PagoProveedorSolicitudProveedor::STAGE_DATA
            ? ($provider->created_at ?? $provider->updated_at)
            : ($provider->updated_at ?? $provider->created_at);
        if (!$since instanceof Carbon) {
            return null;
        }

        return ['at' => $since->copy()->addHours(self::salesTaskHours()), 'kind' => 'task'];
    }

    /**
     * Área que tiene el proveedor y lo que le falta hacer, cuando no es tarea de Ventas.
     * Null si ya no queda nada pendiente (completado o anulado).
     *
     * @return array{area: string, task: string}|null
     */
    private function waitingOn(PagoProveedorSolicitudProveedor $provider): ?array
    {
        if ($provider->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED) {
            return null;
        }
        $refund = $provider->relationLoaded('refunds')
            ? $provider->refunds->first(fn (PagoProveedorReembolso $row) => in_array($row->status, PagoProveedorReembolso::OPEN_STATUSES, true))
            : null;
        if ($refund instanceof PagoProveedorReembolso) {
            $amount = '$'.number_format((float) $refund->amount, 2);

            return $refund->status === PagoProveedorReembolso::STATUS_REQUESTED
                ? ['area' => 'Administración', 'task' => 'aprobar el reembolso de '.$amount.' al cliente']
                : ['area' => 'Ventas', 'task' => 'consultar al cliente el reembolso de '.$amount];
        }
        if ($provider->status === PagoProveedorSolicitudProveedor::STATUS_COMPLETED) {
            return null;
        }
        $payment = (int) $provider->current_payment > PagoProveedorSolicitudProveedor::FIRST_INSTALLMENT ? ' (pago 2)' : '';
        $stage = (int) $provider->stage;
        if ($stage === PagoProveedorSolicitudProveedor::STAGE_VALIDATION) {
            return $provider->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_PENDING
                ? ['area' => 'Administración', 'task' => 'aprobar el pago']
                : ['area' => 'Administración', 'task' => 'verificar el depósito del cliente'];
        }
        if ($stage === PagoProveedorSolicitudProveedor::STAGE_EXECUTION) {
            return $provider->currency === PagoProveedorCotizacionProveedor::CURRENCY_RMB
                ? ['area' => 'Operaciones China', 'task' => 'pagar en yuanes al proveedor'.$payment]
                : ['area' => 'Administración', 'task' => 'pagar el T-T al proveedor'.$payment];
        }
        if ($provider->client_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT
            && (int) $provider->current_installment === PagoProveedorSolicitudProveedor::FIRST_INSTALLMENT
            && $provider->second_installment_on !== null) {
            return ['area' => 'el cliente', 'task' => 'pagar la 2.ª cuota el '.$provider->second_installment_on->format('d/m/Y')];
        }

        return ['area' => 'Administración', 'task' => 'cerrar la confirmación'];
    }

    public static function salesTaskHours(): int
    {
        return max(1, (int) app(PagoProveedorConfiguracionService::class)->value(
            PagoProveedorSolicitudProveedor::SETTING_SALES_TASK_HOURS,
            PagoProveedorSolicitudProveedor::DEFAULT_SALES_TASK_HOURS
        ));
    }
}
