<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorAbono;
use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Services\PagoProveedor\PagoProveedorAprobacionService;
use App\Traits\FileTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AprobacionPagoResource extends JsonResource
{
    use FileTrait;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PagoProveedorSolicitudProveedor $provider */
        $provider = $this->resource;
        $provider->loadMissing(['solicitud', 'datos', 'aplicaciones.abono.aplicaciones']);
        $approval = app(PagoProveedorAprobacionService::class);
        $comparison = $approval->compare($provider);
        $requestModel = $provider->solicitud;
        $deposits = $this->deposits($provider);
        $open = (int) $provider->stage === PagoProveedorSolicitudProveedor::STAGE_VALIDATION
            && $provider->approval_status !== PagoProveedorSolicitudProveedor::APPROVAL_APPROVED
            && $provider->status !== PagoProveedorSolicitudProveedor::STATUS_CANCELLED;

        return [
            'id' => $provider->id,
            'request_id' => $provider->request_id,
            'order_number' => $requestModel?->order_number,
            'client_name' => $requestModel?->client_name,
            'consolidation' => $requestModel?->consolidation,
            'supplier_name' => $provider->supplier_name,
            'supplier_code' => $provider->code_supplier,
            'product' => $provider->product,
            'request_code' => $provider->request_code,
            'payment_type' => $provider->payment_type,
            'currency' => $provider->currency,
            'installment_label' => $provider->client_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT
                ? 'Cuota '.$provider->current_installment.' de 2'
                : null,
            'charged_amount' => $comparison['charged'],
            'received_amount' => $comparison['received'],
            'difference' => $comparison['difference'],
            'category' => $comparison['category'],
            'comparison_label' => $this->comparisonLabel($comparison['difference']),
            'tolerance' => $comparison['tolerance'],
            'exception_limit' => $comparison['exception_limit'],
            'status' => $provider->approval_status,
            'status_label' => $this->statusLabel($provider->approval_status),
            'observation' => $this->observation($provider, $comparison),
            'observation_for_admin' => $provider->approval_status !== PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT,
            'deadline_text' => $this->deadlineText($provider, $deposits),
            'deadline_overdue' => $provider->collect_until ? $provider->collect_until->isPast() : false,
            'approved_at' => optional($provider->approved_at)->toIso8601String(),
            'approval_mode' => $provider->approval_mode,
            'assumed_amount' => $provider->assumed_amount,
            'assumed_reason' => $provider->assumed_reason,
            'collect_amount' => $provider->collect_amount,
            'deposits' => $deposits,
            'can_approve' => $open,
            'can_collect' => $open
                && !in_array($provider->approval_status, [
                    PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT,
                    PagoProveedorSolicitudProveedor::APPROVAL_REGISTRATION_ERROR,
                ], true)
                && $comparison['difference'] < -PagoProveedorSolicitudProveedor::MONEY_EPSILON,
            'can_mark_registration_error' => $open
                && !in_array($provider->approval_status, [
                    PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT,
                    PagoProveedorSolicitudProveedor::APPROVAL_REGISTRATION_ERROR,
                ], true),
            'can_extend' => in_array($provider->approval_status, [
                    PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT,
                    PagoProveedorSolicitudProveedor::APPROVAL_REGISTRATION_ERROR,
                ], true)
                && $provider->collect_until
                && $provider->collect_until->isPast(),
            'can_cancel' => $open,
            'needs_reason' => $approval->needsReason($comparison['category']),
        ];
    }

    /**
     * @param  array{difference: float, category: string, tolerance: float}  $comparison
     */
    private function observation(PagoProveedorSolicitudProveedor $provider, array $comparison): string
    {
        if ($provider->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_APPROVED) {
            $text = $provider->approval_mode === PagoProveedorSolicitudProveedor::APPROVAL_AUTOMATIC
                ? 'Aprobación automática'
                : 'Aprobado por Administración';
            if ($provider->assumed_amount) {
                $text .= '. Faltó $'.number_format((float) $provider->assumed_amount, 2, '.', '').' · asume Pro Business';
                if ($provider->assumed_reason) {
                    $text .= ' ('.$provider->assumed_reason.')';
                }
            }

            return $text;
        }

        if ($provider->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT) {
            $amount = number_format((float) ($provider->collect_amount ?? abs($comparison['difference'])), 2, '.', '');

            return 'Faltan $'.$amount.': Ventas debe cobrarlos al cliente';
        }
        if ($provider->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_REGISTRATION_ERROR) {
            return 'Error de registro: Ventas debe corregir el voucher o los datos';
        }

        if ($provider->collect_until && $provider->collect_until->isPast()) {
            return 'Plazo de cobro vencido';
        }

        return match ($comparison['category']) {
            PagoProveedorSolicitudProveedor::GAP_SHORT,
            PagoProveedorSolicitudProveedor::GAP_SHORT_EXCEPTION => 'Faltan $'.number_format(abs($comparison['difference']), 2, '.', '').': supera la tolerancia',
            PagoProveedorSolicitudProveedor::GAP_OVER => 'Sobran $'.number_format($comparison['difference'], 2, '.', '').': supera la tolerancia',
            default => 'Listo para aprobar',
        };
    }

    private function comparisonLabel(float $difference): string
    {
        if (abs($difference) < PagoProveedorSolicitudProveedor::MONEY_EPSILON) {
            return 'Importes coinciden';
        }
        $amount = number_format(abs($difference), 2, '.', '');

        return $difference < 0 ? 'Faltan $'.$amount : 'Sobran $'.$amount;
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT => 'Por cobrar',
            PagoProveedorSolicitudProveedor::APPROVAL_REGISTRATION_ERROR => 'Error de registro',
            PagoProveedorSolicitudProveedor::APPROVAL_APPROVED => 'Aprobado',
            default => 'Pendiente',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $deposits
     */
    private function deadlineText(PagoProveedorSolicitudProveedor $provider, array $deposits): ?string
    {
        if (in_array($provider->approval_status, [
            PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT,
            PagoProveedorSolicitudProveedor::APPROVAL_REGISTRATION_ERROR,
        ], true) && $provider->collect_until) {
            $days = (int) now()->startOfDay()->diffInDays($provider->collect_until->copy()->startOfDay(), false);
            if ($days < 0) {
                $gone = abs($days);

                return 'vencido hace '.$gone.' día'.($gone === 1 ? '' : 's');
            }

            return 'quedan '.$days.' día'.($days === 1 ? '' : 's');
        }

        $oldest = null;
        foreach ($deposits as $deposit) {
            if (!empty($deposit['paid_on']) && ($oldest === null || $deposit['paid_on'] < $oldest)) {
                $oldest = $deposit['paid_on'];
            }
        }
        if (!$oldest) {
            return null;
        }
        $days = (int) now()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($oldest)->startOfDay(), false);
        $age = abs($days);

        return $age === 0 ? 'hoy' : 'hace '.$age.' día'.($age === 1 ? '' : 's');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function deposits(PagoProveedorSolicitudProveedor $provider): array
    {
        $rows = [];
        foreach ($provider->aplicaciones as $application) {
            $deposit = $application->abono;
            if (!$deposit || $deposit->status === PagoProveedorAbono::STATUS_CANCELLED) {
                continue;
            }
            $sharedCount = $deposit->aplicaciones->count();
            $rows[] = [
                'id' => $deposit->id,
                'paid_on' => optional($deposit->paid_on)->toDateString(),
                'bank' => $deposit->bank,
                'bank_label' => $deposit->bank === PagoProveedorAbono::BANK_INTERBANK ? 'Interbank' : 'BCP',
                'operation_number' => $deposit->operation_number,
                'status' => $deposit->status,
                'status_label' => match ($deposit->status) {
                    PagoProveedorAbono::STATUS_VALIDATED => 'Validado',
                    PagoProveedorAbono::STATUS_OBSERVED => 'Observado',
                    default => 'Pendiente',
                },
                'bank_amount' => $deposit->bank_amount ?? $deposit->amount,
                'recorded_amount' => $deposit->recorded_amount,
                'applied_amount' => (float) $application->amount,
                'shared' => $sharedCount > 1,
                'shared_count' => max(0, $sharedCount - 1),
                'voucher_url' => $deposit->voucher_path ? $this->cdnStorageUrl($deposit->voucher_path) : null,
            ];
        }

        return $rows;
    }
}
