<?php

namespace App\Services\PagoProveedor;

use Carbon\Carbon;

/**
 * Código interno CN/PE + correlativo + mes + año (RN-03). No se muestra al cliente.
 * El formato definitivo entre socios sigue abierto (PV-01); este es el de la maqueta.
 */
class PagoProveedorCodigoSolicitud
{
    public function armar(string $moneda, int $correlativo, ?Carbon $fecha = null): string
    {
        $fecha = ($fecha ?: Carbon::now(PagoProveedorVigenciaService::ZONA))->timezone(PagoProveedorVigenciaService::ZONA);
        $prefijo = strtoupper($moneda) === 'RMB' ? 'CN' : 'PE';
        $meses = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

        return $prefijo
            . str_pad((string) $correlativo, 2, '0', STR_PAD_LEFT)
            . $meses[(int) $fecha->format('n') - 1]
            . $fecha->format('y');
    }
}
