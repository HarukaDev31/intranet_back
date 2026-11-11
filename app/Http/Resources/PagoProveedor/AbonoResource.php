<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorAbono;
use App\Models\PagoProveedor\PagoProveedorAbonoAplicacion;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Services\PagoProveedor\PagoProveedorConfiguracionService;
use App\Traits\FileTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbonoResource extends JsonResource
{
    use FileTrait;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PagoProveedorAbono $abono */
        $abono = $this->resource;
        $solicitud = $abono->solicitud;
        $advanced = $abono->aplicaciones->contains(function (PagoProveedorAbonoAplicacion $aplicacion) use ($solicitud) {
            $provider = $solicitud?->proveedores?->firstWhere('id', $aplicacion->request_provider_id);

            return $provider && (int) $provider->stage > PagoProveedorSolicitudProveedor::STAGE_DATA;
        });

        $hours = max(1, (int) app(PagoProveedorConfiguracionService::class)->value(PagoProveedorSolicitudProveedor::SETTING_DECISION_HOURS, 72));
        $decideUntil = null;
        $decideOverdue = false;
        $decideText = null;
        if ($abono->surplus_decision === PagoProveedorAbono::EXCESS_TO_CONSULT && $abono->updated_at) {
            $decideUntil = $abono->updated_at->copy()->addHours($hours);
            $decideOverdue = $decideUntil->isPast();
            $raw = \App\Services\PagoProveedor\PagoProveedorPlazo::remainingText($decideUntil, 'para decidir');
            $decideText = $decideOverdue ? 'plazo de decisión vencido' : $raw;
        }

        // Plazo de Administración para validar el depósito (mismo cálculo que la bandeja de Verificación).
        $validacion = null;
        if ($abono->status === PagoProveedorAbono::STATUS_IN_VALIDATION && $abono->created_at) {
            $verifyHours = max(1, (int) app(PagoProveedorConfiguracionService::class)->value(PagoProveedorSolicitudProveedor::SETTING_VERIFY_HOURS, 2));
            $deadline = $abono->created_at->copy()->addHours($verifyHours);
            $remaining = (int) now()->diffInMinutes($deadline, false);
            $validacion = [
                'registered_at' => $abono->created_at->toIso8601String(),
                'deadline_at' => $deadline->toIso8601String(),
                'server_now' => now()->toIso8601String(),
                'hours' => $verifyHours,
                'remaining_minutes' => $remaining,
                'overdue' => $remaining < 0,
                'text' => \App\Services\PagoProveedor\PagoProveedorPlazo::remainingText($deadline),
            ];
        }

        return [
            'id' => $abono->id,
            'numero' => $abono->numero(),
            'validacion_sla' => $validacion,
            'importe' => $abono->amount,
            'fecha_pago' => optional($abono->paid_on)->format('Y-m-d'),
            'numero_operacion' => $abono->operation_number,
            'banco' => $abono->bank,
            'interbancaria' => $abono->interbank,
            'voucher_nombre' => $abono->voucher_name,
            'voucher_url' => $this->cdnStorageUrl($abono->voucher_path),
            'estado' => $abono->status,
            'estado_nombre' => self::statusLabel($abono->status),
            'excedente' => $abono->surplus,
            'excedente_decision' => $abono->surplus_decision,
            'excedente_decide_until' => optional($decideUntil)->toIso8601String(),
            'excedente_decide_overdue' => $decideOverdue,
            'excedente_decide_text' => $decideText,
            'operacion_repetida' => $abono->repeated_operation,
            // Sales corrects an observed deposit in any stage; voiding stays limited to stage 1 (RN-45, RN-56).
            'puede_editar' => $abono->status === PagoProveedorAbono::STATUS_OBSERVED
                || ($abono->status === PagoProveedorAbono::STATUS_IN_VALIDATION && !$advanced)
                || \App\Services\PagoProveedor\PagoProveedorEtapa1Service::abonoCorregibleEnValidacion($solicitud, $abono)
                || ($abono->status !== PagoProveedorAbono::STATUS_CANCELLED && $abono->aplicaciones->contains(function (PagoProveedorAbonoAplicacion $aplicacion) use ($solicitud) {
                    $provider = $solicitud?->proveedores?->firstWhere('id', $aplicacion->request_provider_id);

                    return $provider && $provider->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_REGISTRATION_ERROR;
                })),
            'puede_anular' => in_array($abono->status, [PagoProveedorAbono::STATUS_IN_VALIDATION, PagoProveedorAbono::STATUS_OBSERVED], true) && !$advanced,
            'observacion' => $abono->status === PagoProveedorAbono::STATUS_OBSERVED ? [
                'motivo' => app(\App\Services\PagoProveedor\PagoProveedorVerificacionService::class)->reasonLabel((string) $abono->observation_reason),
                'detalle' => $abono->observation_detail,
                'plazo' => $abono->observe_until ? \App\Services\PagoProveedor\PagoProveedorPlazo::remainingText($abono->observe_until) : null,
            ] : null,
            'puede_elegir_continuacion' => $abono->status === PagoProveedorAbono::STATUS_VALIDATED
                && $abono->aplicaciones->count() > 1
                && $abono->aplicaciones->contains(fn (PagoProveedorAbonoAplicacion $aplicacion) => $aplicacion->continues === null)
                && (float) $abono->aplicaciones->sum('amount') - (float) ($abono->bank_amount ?? $abono->amount)
                    > (float) app(PagoProveedorConfiguracionService::class)->value(PagoProveedorSolicitudProveedor::SETTING_TOLERANCE, 1) + 0.001,
            'aplicaciones' => $abono->aplicaciones->map(fn (PagoProveedorAbonoAplicacion $aplicacion) => [
                'solicitud_proveedor_id' => $aplicacion->request_provider_id,
                'monto' => $aplicacion->amount,
                'continua' => $aplicacion->continues,
            ])->values()->all(),
        ];
    }

    public static function statusLabel(string $status): string
    {
        return [
            PagoProveedorAbono::STATUS_IN_VALIDATION => 'En validación',
            PagoProveedorAbono::STATUS_VALIDATED => 'Validado',
            PagoProveedorAbono::STATUS_OBSERVED => 'Observado',
            PagoProveedorAbono::STATUS_CANCELLED => 'Anulado',
        ][$status] ?? $status;
    }
}
