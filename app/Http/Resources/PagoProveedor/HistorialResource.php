<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorHistorial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistorialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PagoProveedorHistorial $event */
        $event = $this->resource;

        return [
            'accion' => $event->action,
            'detalle' => $event->detail,
            'usuario' => trim((string) $event->user_name . ' · ' . (string) $event->area, ' ·'),
            'fecha' => optional($event->created_at)->toIso8601String(),
        ];
    }
}
