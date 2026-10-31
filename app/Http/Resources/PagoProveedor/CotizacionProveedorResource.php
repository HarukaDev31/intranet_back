<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Traits\FileTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CotizacionProveedorResource extends JsonResource
{
    use FileTrait;

    /** @var array<string, mixed>|null */
    private ?array $line = null;

    /**
     * @param  array<string, mixed>|null  $line
     */
    public function withLine(?array $line): self
    {
        $this->line = $line;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PagoProveedorCotizacionProveedor $provider */
        $provider = $this->resource;

        return [
            'id' => $provider->id,
            'orden' => $provider->position,
            'code_supplier' => $provider->code_supplier,
            'consolidated_provider_id' => $provider->consolidatedProvider?->id ?? $provider->consolidated_provider_id,
            'id_proveedor_cc' => $provider->consolidated_provider_id,
            'nombre_proveedor' => $provider->supplier_name,
            'producto' => $provider->product,
            'moneda' => $provider->currency,
            'tipo_pago' => $provider->payment_type,
            'valor_carga_usd' => $provider->cargo_value_usd,
            'modalidad_abono' => $provider->client_payment_mode,
            'modalidad_pago' => $provider->supplier_payment_mode,
            'porcentaje_1' => $provider->first_share_percent,
            'porcentaje_2' => $provider->second_share_percent,
            'fecha_cuota_2' => optional($provider->second_installment_on)->format('Y-m-d'),
            'comision_bancaria' => $provider->charges_bank_fee,
            'comision_administrativa' => $provider->charges_admin_fee,
            'proforma_nombre' => $provider->proforma_name,
            'proforma_url' => $provider->proforma_path ? $this->cdnStorageUrl($provider->proforma_path) : null,
            'calculo' => $this->line,
        ];
    }
}
