<?php

namespace App\Services\PagoProveedor;

/**
 * Valor en yuanes de registros guardados antes de tener el monto en yuanes digitado por Ventas.
 */
class PagoProveedorYuanes
{
    /**
     * Si un monto entero en yuanes da el mismo USD guardado (¥10,000 ÷ 7 = $1,428.57, con 1 centavo de margen),
     * devuelve ese entero y no el reconvertido (¥9,999.99 / ¥40,000.03).
     */
    public static function desdeUsd(float $usd, float $tc): float
    {
        $exacto = round($usd * $tc, 2);
        if ($tc <= 0) {
            return $exacto;
        }
        $entero = round($usd * $tc);

        return abs(round($entero / $tc, 2) - round($usd, 2)) <= 0.011 ? $entero : $exacto;
    }
}
