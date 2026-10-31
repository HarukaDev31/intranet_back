<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorSolicitud;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PagoProveedorSolicitud $solicitud */
        $solicitud = $this->resource;
        $solicitud->loadMissing(['proveedores.datos', 'proveedores.consolidatedProvider', 'cotizacion']);
        $solicitud->proveedores->each(fn ($provider) => $provider->setRelation('solicitud', $solicitud));

        return [
            'id' => $solicitud->id,
            'numero_orden' => $solicitud->order_number,
            'cotizacion_id' => $solicitud->quote_id,
            'cotizacion_codigo' => $solicitud->cotizacion?->code,
            'cod_cotizacion_cc' => $solicitud->consolidated_quote_code,
            'cliente_nombre' => $solicitud->client_name,
            'consolidado' => $solicitud->consolidation,
            'proveedores_cantidad' => $solicitud->proveedores->count(),
            'creado_en' => optional($solicitud->created_at)->toIso8601String(),
            'providers' => SolicitudProveedorResource::collection($solicitud->proveedores),
        ];
    }
}
