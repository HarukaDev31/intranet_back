<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\Usuario;

class PagoProveedorAutorizacion
{
    /**
     * Cotizaciones y solicitudes de esta entrega las gestiona Ventas (cargo Cotizador).
     *
     * @return array{id: int|null, nombre: string, area: string}
     */
    public function exigirVentas(Usuario $usuario): array
    {
        $usuario->loadMissing('grupo');
        $grupoRelacion = $usuario->getRelationValue('grupo');
        $grupo = trim((string) ($grupoRelacion instanceof \Illuminate\Database\Eloquent\Model ? $grupoRelacion->getAttribute('No_Grupo') : ''));

        if (!in_array($grupo, ['Cotizador', 'Admin'], true)) {
            throw new PagoProveedorException('No tienes permiso para gestionar pago a proveedor.', 403);
        }

        return [
            'id' => $usuario->getAttribute('ID_Usuario') !== null ? (int) $usuario->getAttribute('ID_Usuario') : null,
            'nombre' => (string) ($usuario->getAttribute('No_Nombres_Apellidos') ?: $usuario->getAttribute('No_Usuario')),
            'area' => $grupo === 'Cotizador' ? 'Ventas' : $grupo,
        ];
    }
}
