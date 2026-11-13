<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorDatoProveedor;
use App\Traits\FileTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormularioTtResource extends JsonResource
{
    use FileTrait;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PagoProveedorDatoProveedor $datos */
        $datos = $this->resource;
        $datos->loadMissing('proveedor');
        $provider = $datos->proveedor;

        return [
            'proveedor' => $provider?->code_supplier,
            'nombre_proveedor' => $provider?->supplier_name,
            'producto' => $provider?->product,
            'proforma_nombre' => $provider?->proforma_name,
            'proforma_url' => $provider?->proforma_path ? $this->cdnStorageUrl($provider->proforma_path) : null,
            'estado' => $datos->status,
            'correccion_solicitada' => $datos->correction_requested,
            'bloqueado' => $datos->status === PagoProveedorDatoProveedor::STATUS_RECEIVED && !$datos->correction_requested,
            'puede_editar' => $datos->correction_requested || $datos->status !== PagoProveedorDatoProveedor::STATUS_RECEIVED,
            'datos' => [
                'beneficiario_nombre' => $datos->beneficiary_name,
                'beneficiario_cuenta' => $datos->beneficiary_account,
                'beneficiario_direccion' => $datos->beneficiary_address,
                'beneficiario_pais' => $datos->beneficiary_country,
                'beneficiario_ciudad' => $datos->beneficiary_city,
                'banco_tipo_codigo' => $datos->bank_code_type,
                'banco_codigo' => $datos->bank_code,
                'banco_nombre' => $datos->bank_name,
                'banco_direccion' => $datos->bank_address,
                'banco_pais' => $datos->bank_country,
                'banco_ciudad' => $datos->bank_city,
                'usa_intermediario' => $datos->uses_intermediary,
                'inter_tipo_codigo' => $datos->intermediary_code_type,
                'inter_codigo' => $datos->intermediary_code,
                'inter_nombre' => $datos->intermediary_name,
                'inter_direccion' => $datos->intermediary_address,
                'inter_pais' => $datos->intermediary_country,
                'inter_ciudad' => $datos->intermediary_city,
            ],
        ];
    }
}
