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
            'entidad_tipo' => $entidadTipo,
            'entidad_id' => $entidadId,
            'accion' => $accion,
            'detalle' => $detalle,
            'usuario_id' => $actor['id'],
            'usuario_nombre' => $actor['nombre'],
            'area' => $actor['area'],
            'created_at' => Carbon::now(),
        ]);
    }
}
