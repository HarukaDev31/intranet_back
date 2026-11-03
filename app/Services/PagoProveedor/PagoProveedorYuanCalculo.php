<?php

namespace App\Services\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorEjecucion;

/**
 * Diferencia de un pago en yuanes (RN-92): yuanes obtenidos = USD × tipo de cambio real.
 * Si la diferencia en dólares cabe en la tolerancia, la asume Pro Business.
 */
class PagoProveedorYuanCalculo
{
    /**
     * @return array{obtained: float, difference_yuan: float, difference_usd: float, result: string}
     */
    public static function calcular(float $usd, float $realRate, float $yuanPaid, float $tolerance): array
    {
        $obtained = round($usd * $realRate, 2);
        $differenceYuan = round($obtained - $yuanPaid, 2);
        $differenceUsd = $realRate > 0 ? round($differenceYuan / $realRate, 2) : 0.0;
        $within = abs($differenceUsd) <= $tolerance + 0.001;

        if ($within) {
            $result = PagoProveedorEjecucion::RESULT_WITHIN;
        } elseif ($differenceUsd < 0) {
            $result = PagoProveedorEjecucion::RESULT_COLLECT;
        } else {
            $result = PagoProveedorEjecucion::RESULT_REFUND;
        }

        return [
            'obtained' => $obtained,
            'difference_yuan' => $differenceYuan,
            'difference_usd' => $differenceUsd,
            'result' => $result,
        ];
    }
}
