<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorHistorial;
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

        if ($grupo !== Usuario::ROL_COTIZADOR) {
            throw new PagoProveedorException('No tienes permiso para gestionar pago a proveedor.', 403);
        }

        return $this->actor($usuario, PagoProveedorHistorial::AREA_SALES);
    }

    /**
     * Verificación de depósitos la hace Administración.
     *
     * @return array{id: int|null, nombre: string, area: string}
     */
    public function requireAdministration(Usuario $usuario): array
    {
        $usuario->loadMissing('grupo');
        $grupoRelacion = $usuario->getRelationValue('grupo');
        $grupo = trim((string) ($grupoRelacion instanceof \Illuminate\Database\Eloquent\Model ? $grupoRelacion->getAttribute('No_Grupo') : ''));

        if ($grupo !== Usuario::ROL_ADMINISTRACION) {
            throw new PagoProveedorException('No tienes permiso para verificar depósitos.', 403);
        }

        return $this->actor($usuario, PagoProveedorHistorial::AREA_ADMINISTRATION);
    }

    /**
     * Los pagos en yuanes los registra Operaciones China (cargo ContenedorAlmacen).
     *
     * @return array{id: int|null, nombre: string, area: string}
     */
    public function requireWarehouse(Usuario $usuario): array
    {
        $usuario->loadMissing('grupo');
        $grupoRelacion = $usuario->getRelationValue('grupo');
        $grupo = trim((string) ($grupoRelacion instanceof \Illuminate\Database\Eloquent\Model ? $grupoRelacion->getAttribute('No_Grupo') : ''));

        if ($grupo !== Usuario::ROL_ALMACEN_CHINA) {
            throw new PagoProveedorException('No tienes permiso para pagar en yuanes.', 403);
        }

        return $this->actor($usuario, PagoProveedorHistorial::AREA_CHINA);
    }

    /**
     * Ventas gestiona la solicitud y Administración la consulta en solo lectura.
     * Operaciones China no entra al detalle: solo ve nombre del cliente y consolidado (RN-98).
     *
     * @return array{id: int|null, nombre: string, area: string}
     */
    public function requireRequestReader(Usuario $usuario): array
    {
        $usuario->loadMissing('grupo');
        $grupoRelacion = $usuario->getRelationValue('grupo');
        $grupo = trim((string) ($grupoRelacion instanceof \Illuminate\Database\Eloquent\Model ? $grupoRelacion->getAttribute('No_Grupo') : ''));

        $area = match ($grupo) {
            Usuario::ROL_COTIZADOR => PagoProveedorHistorial::AREA_SALES,
            Usuario::ROL_ADMINISTRACION => PagoProveedorHistorial::AREA_ADMINISTRATION,
            default => throw new PagoProveedorException('No tienes permiso para ver esta solicitud.', 403),
        };

        return $this->actor($usuario, $area);
    }

    /**
     * @return array{id: int|null, nombre: string, area: string}
     */
    private function actor(Usuario $usuario, string $area): array
    {
        return [
            'id' => $usuario->getAttribute('ID_Usuario') !== null ? (int) $usuario->getAttribute('ID_Usuario') : null,
            'nombre' => (string) ($usuario->getAttribute('No_Nombres_Apellidos') ?: $usuario->getAttribute('No_Usuario')),
            'area' => $area,
        ];
    }
}
