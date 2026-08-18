<?php

namespace App\Services\CargaConsolidada\CotizacionFinal;

class TarifaTipoClienteCalculator
{
    /**
     * Tarifa por tipo de cliente y CBM. Rangos con límite inferior inclusive
     * para no dejar fuera volúmenes exactos (0.59, 1.00, 2.00, 3.00).
     */
    public static function calculate($tipoCliente, $volumen, $tarifaBase = 0): float
    {
        $tipoCliente = trim(strtoupper((string) $tipoCliente));
        $volumen = is_numeric($volumen) ? round((float) $volumen, 2) : 0.0;
        $tarifaBase = is_numeric($tarifaBase) ? (float) $tarifaBase : 0.0;

        if ($tipoCliente === 'SOCIO') {
            return 250.0;
        }

        if ($volumen <= 0) {
            return $tarifaBase;
        }

        if ($tipoCliente === 'NUEVO') {
            if ($volumen < 0.59) {
                return 280.0;
            }
            if ($volumen < 1.00) {
                return 375.0;
            }
            if ($volumen < 2.00) {
                return 375.0;
            }
            if ($volumen < 3.00) {
                return 350.0;
            }
            if ($volumen <= 4.10) {
                return 325.0;
            }

            return 300.0;
        }

        if ($tipoCliente === 'ANTIGUO') {
            if ($volumen < 0.59) {
                return 260.0;
            }
            if ($volumen < 1.00) {
                return 350.0;
            }
            if ($volumen <= 2.09) {
                return 350.0;
            }
            if ($volumen <= 3.09) {
                return 325.0;
            }
            if ($volumen <= 4.10) {
                return 300.0;
            }

            return 280.0;
        }

        return $tarifaBase;
    }
}
