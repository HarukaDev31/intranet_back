<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorDatoProveedor;
use App\Models\PagoProveedor\PagoProveedorEjecucion;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Services\PagoProveedor\PagoProveedorEjecucionService;
use App\Traits\FileTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EjecucionPagoResource extends JsonResource
{
    use FileTrait;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $execution = $this->resource instanceof PagoProveedorEjecucion ? $this->resource : null;
        /** @var PagoProveedorSolicitudProveedor $provider */
        $provider = $execution instanceof PagoProveedorEjecucion && $execution->proveedor !== null
            ? $execution->proveedor
            : $this->resource;
        $provider->loadMissing(['solicitud', 'datos']);
        $solicitud = $provider->solicitud;
        $data = $provider->datos;
        $amounts = $execution ? [
            'cargo' => (float) $execution->cargo_amount,
            'charged_bank_fee' => (float) $execution->charged_bank_fee,
            'charged_admin_fee' => (float) $execution->charged_admin_fee,
        ] : app(PagoProveedorEjecucionService::class)->amounts($provider);
        $real = $execution ? (float) $execution->real_bank_fee : null;
        $difference = $real === null ? null : round($amounts['charged_bank_fee'] - $real, 2);
        $profit = $difference === null ? null : round($difference + $amounts['charged_admin_fee'], 2);

        return [
            'id' => $execution instanceof PagoProveedorEjecucion ? $execution->id : $provider->id,
            'provider_id' => $provider->id,
            'request_id' => $provider->request_id,
            'order_number' => $solicitud?->order_number,
            'client_name' => $solicitud?->client_name,
            'consolidation' => $solicitud?->consolidation,
            'supplier_name' => $provider->supplier_name,
            'supplier_code' => $provider->code_supplier,
            'product' => $provider->product,
            'request_code' => $provider->request_code,
            'currency' => $provider->currency,
            'payment_type' => $provider->payment_type,
            'installment_label' => $provider->client_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT
                ? 'Cuota '.(($execution instanceof PagoProveedorEjecucion ? $execution->installment : null) ?? $provider->current_installment).' de 2'
                : null,
            'supplier_payment_label' => $provider->supplier_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT
                ? 'Pago '.(($execution instanceof PagoProveedorEjecucion ? $execution->supplier_payment : null) ?? $provider->current_payment).' de 2'
                : null,
            'status' => $execution ? PagoProveedorEjecucion::STATUS_PAID : PagoProveedorEjecucion::STATUS_PENDING,
            'status_label' => $execution ? 'Pagado' : 'Pendiente',
            'observation' => $execution ? 'Constancia registrada' : 'Listo para transferir',
            'cargo_amount' => $amounts['cargo'],
            'charged_bank_fee' => $amounts['charged_bank_fee'],
            'real_bank_fee' => $real,
            'charged_admin_fee' => $amounts['charged_admin_fee'],
            'bank_difference' => $difference,
            'profit' => $profit,
            'executed_on' => $execution?->executed_on?->toDateString(),
            'receipt_name' => $execution?->receipt_name,
            'receipt_url' => $execution?->receipt_path ? $this->cdnStorageUrl($execution->receipt_path) : null,
            'bank_ready' => app(PagoProveedorEjecucionService::class)->bankReady($provider),
            'can_register' => $execution === null,
            'deposit_bank' => app(PagoProveedorEjecucionService::class)->clientBank($provider),
            'bank' => $this->bank($data),
            'bank_copy_text' => $this->copyText($data),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bank(?PagoProveedorDatoProveedor $data): array
    {
        if (!$data) {
            return [];
        }

        return [
            'beneficiary_name' => $data->beneficiary_name,
            'beneficiary_account' => $data->beneficiary_account,
            'beneficiary_address' => $data->beneficiary_address,
            'beneficiary_country' => $data->beneficiary_country,
            'beneficiary_city' => $data->beneficiary_city,
            'bank_code_type' => $data->bank_code_type,
            'bank_code' => $data->bank_code,
            'bank_name' => $data->bank_name,
            'bank_address' => $data->bank_address,
            'bank_country' => $data->bank_country,
            'bank_city' => $data->bank_city,
            'uses_intermediary' => (bool) $data->uses_intermediary,
            'intermediary_name' => $data->intermediary_name,
            'intermediary_code_type' => $data->intermediary_code_type,
            'intermediary_code' => $data->intermediary_code,
            'intermediary_address' => $data->intermediary_address,
            'intermediary_country' => $data->intermediary_country,
            'intermediary_city' => $data->intermediary_city,
        ];
    }

    private function copyText(?PagoProveedorDatoProveedor $data): string
    {
        if (!$data) {
            return '';
        }
        $lines = [
            'Beneficiario: '.$data->beneficiary_name,
            'Cuenta: '.$data->beneficiary_account,
            'Dirección: '.$data->beneficiary_address,
            'País: '.$data->beneficiary_country,
            'Ciudad: '.$data->beneficiary_city,
            'Banco: '.$data->bank_name,
            ($data->bank_code_type ?: 'Código').': '.$data->bank_code,
            'Dirección del banco: '.$data->bank_address,
            'País del banco: '.$data->bank_country,
            'Ciudad del banco: '.$data->bank_city,
        ];
        if ($data->uses_intermediary) {
            $lines[] = 'Intermediario: '.$data->intermediary_name;
            $lines[] = ($data->intermediary_code_type ?: 'Código intermediario').': '.$data->intermediary_code;
            $lines[] = 'Dirección intermediario: '.$data->intermediary_address;
            $lines[] = 'País intermediario: '.$data->intermediary_country;
            $lines[] = 'Ciudad intermediario: '.$data->intermediary_city;
        }

        return implode("\n", $lines);
    }
}
