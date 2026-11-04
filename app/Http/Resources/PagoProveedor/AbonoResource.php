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

        return [
            'id' => $abono->id,
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
            'puede_editar' => in_array($abono->status, [PagoProveedorAbono::STATUS_IN_VALIDATION, PagoProveedorAbono::STATUS_OBSERVED], true) && !$advanced,
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
