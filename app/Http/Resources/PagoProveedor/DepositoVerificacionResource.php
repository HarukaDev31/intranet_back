<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorAbono;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Services\PagoProveedor\PagoProveedorConfiguracionService;
use App\Services\PagoProveedor\PagoProveedorVerificacionService;
use App\Traits\FileTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepositoVerificacionResource extends JsonResource
{
    use FileTrait;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PagoProveedorAbono $deposit */
        $deposit = $this->resource;
        $solicitud = $deposit->solicitud;
        $movedOn = app(PagoProveedorVerificacionService::class)->providerMovedOn($deposit);
        $hours = max(1, (int) app(PagoProveedorConfiguracionService::class)->value(PagoProveedorSolicitudProveedor::SETTING_VERIFY_HOURS, 2));
        $deadline = $deposit->created_at?->copy()->addHours($hours);
        $remaining = $deadline ? (int) now()->diffInMinutes($deadline, false) : 0;
        $open = in_array($deposit->status, [PagoProveedorAbono::STATUS_IN_VALIDATION, PagoProveedorAbono::STATUS_OBSERVED], true);
        $verified = in_array($deposit->status, [PagoProveedorAbono::STATUS_VALIDATED, PagoProveedorAbono::STATUS_OBSERVED], true);
        $minutesSince = $deposit->verified_at ? (int) abs($deposit->verified_at->diffInMinutes(now())) : null;
        $silentMinutes = max(1, (int) app(PagoProveedorConfiguracionService::class)->value(PagoProveedorSolicitudProveedor::SETTING_UNDO_MINUTES, 5));
        $noticeHours = max(1, (int) app(PagoProveedorConfiguracionService::class)->value(PagoProveedorSolicitudProveedor::SETTING_UNDO_HOURS, 24));
        $withinUndo = $minutesSince !== null && $minutesSince <= $noticeHours * 60;

        $deposit->loadMissing('exchangeProvider');
        $providers = $deposit->aplicaciones->map(function ($application) use ($solicitud) {
            $provider = $solicitud?->proveedores?->firstWhere('id', $application->request_provider_id);

            return [
                'id' => $application->request_provider_id,
                'supplier_code' => $provider?->code_supplier,
                'supplier_name' => $provider?->supplier_name,
                'request_code' => $provider?->request_code,
                'amount' => $application->amount,
            ];
        })->values()->all();
        if ($providers === [] && $deposit->purpose === PagoProveedorAbono::PURPOSE_EXCHANGE && $deposit->exchangeProvider) {
            $providers[] = [
                'id' => $deposit->exchangeProvider->id,
                'supplier_code' => $deposit->exchangeProvider->code_supplier,
                'supplier_name' => $deposit->exchangeProvider->supplier_name,
                'request_code' => $deposit->exchangeProvider->request_code,
                'amount' => (float) $deposit->amount,
            ];
        }

        return [
            'id' => $deposit->id,
            'request_id' => $deposit->request_id,
            'order_number' => $solicitud?->order_number,
            'client_name' => $solicitud?->client_name,
            'client_document' => $solicitud?->client_document,
            'client_whatsapp' => $solicitud?->client_whatsapp,
            'consolidation' => $solicitud?->consolidation,
            'bank' => $deposit->bank,
            'bank_label' => $deposit->bank === PagoProveedorAbono::BANK_INTERBANK ? 'Interbank' : 'BCP',
            'interbank' => $deposit->interbank,
            'paid_on' => optional($deposit->paid_on)->format('Y-m-d'),
            'operation_number' => $deposit->operation_number,
            'amount' => $deposit->amount,
            'bank_amount' => $deposit->bank_amount,
            'recorded_amount' => $deposit->recorded_amount,
            'voucher_name' => $deposit->voucher_name,
            'voucher_url' => $this->cdnStorageUrl($deposit->voucher_path),
            'status' => $deposit->status,
            'status_label' => self::statusLabel($deposit->status),
            'purpose' => $deposit->purpose ?: PagoProveedorAbono::PURPOSE_CLIENT,
            'purpose_label' => $deposit->purpose === PagoProveedorAbono::PURPOSE_EXCHANGE ? 'Diferencia de cambio' : 'Abono del cliente',
            'deadline_text' => $deposit->status === PagoProveedorAbono::STATUS_IN_VALIDATION
                ? ($remaining >= 0 ? 'quedan '.$this->duration($remaining) : 'vencido hace '.$this->duration(abs($remaining)))
                : null,
            'deadline_overdue' => $deposit->status === PagoProveedorAbono::STATUS_IN_VALIDATION && $remaining < 0,
            'observation_reason' => $deposit->observation_reason,
            'observation_detail' => $deposit->observation_detail,
            'corrected_by_admin' => $deposit->corrected_by_admin,
            'verified_at' => optional($deposit->verified_at)->toIso8601String(),
            'providers' => $providers,
            'shared' => count($providers) > 1,
            'can_validate' => $open && !$movedOn,
            'can_undo' => $verified && !$movedOn && $withinUndo,
            'undo_notifies_sales' => $minutesSince !== null && $minutesSince > $silentMinutes,
        ];
    }

    public static function statusLabel(string $status): string
    {
        return [
            PagoProveedorAbono::STATUS_IN_VALIDATION => 'Pendiente',
            PagoProveedorAbono::STATUS_VALIDATED => 'Validado',
            PagoProveedorAbono::STATUS_OBSERVED => 'Observado',
            PagoProveedorAbono::STATUS_CANCELLED => 'Anulado',
        ][$status] ?? $status;
    }

    private function duration(int $minutes): string
    {
        $minutes = max(0, $minutes);
        if ($minutes < 60) {
            return $minutes.' min';
        }
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest ? $hours.' h '.$rest.' min' : $hours.' h';
    }
}
