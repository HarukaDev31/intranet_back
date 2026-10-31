<?php

namespace App\Services\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorHistorial;
use Illuminate\Support\Carbon;

class PagoProveedorHistorialService
{
    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function registrar(string $entidadTipo, int $entidadId, string $accion, array $actor, ?string $detalle = null): void
    {
        PagoProveedorHistorial::query()->create([
            'entity_type' => $entidadTipo,
            'entity_id' => $entidadId,
            'action' => $accion,
            'detail' => $detalle,
            'user_id' => $actor['id'],
            'user_name' => $actor['nombre'],
            'area' => $actor['area'],
            'created_at' => Carbon::now(),
        ]);
    }
}
