<?php

namespace App\Services\CalculadoraImportacion;

/**
 * CBM que multiplica la tarifa de una cotización.
 * Compara el volumen total contra el peso total (kg / 1000) y usa el mayor.
 * No mezcla el criterio proveedor por proveedor.
 */
class CbmFacturable
{
    /**
     * @param iterable<int, mixed> $proveedores
     * @return array{sum_cbm: float, sum_peso: float, peso_cbm: float, domina_peso: bool, facturable: float}
     */
    public static function desdeProveedores(iterable $proveedores): array
    {
        $sumCbm = 0.0;
        $sumPeso = 0.0;
        foreach ($proveedores as $proveedor) {
            $sumCbm += self::numero(self::leer($proveedor, 'cbm'));
            $sumPeso += self::numero(self::leer($proveedor, 'peso'));
        }

        $pesoCbm = $sumPeso / 1000;
        $dominaPeso = $pesoCbm > $sumCbm;

        return [
            'sum_cbm' => $sumCbm,
            'sum_peso' => $sumPeso,
            'peso_cbm' => $pesoCbm,
            'domina_peso' => $dominaPeso,
            'facturable' => $dominaPeso ? $pesoCbm : $sumCbm,
        ];
    }

    public static function aporte(float $cbm, float $peso, bool $dominaPeso): float
    {
        return $dominaPeso ? ($peso / 1000) : $cbm;
    }

    /**
     * @param mixed $proveedor
     * @return mixed
     */
    private static function leer($proveedor, string $campo)
    {
        if (is_array($proveedor)) {
            return $proveedor[$campo] ?? 0;
        }
        if (is_object($proveedor)) {
            return $proveedor->{$campo} ?? 0;
        }

        return 0;
    }

    /**
     * @param mixed $valor
     */
    private static function numero($valor): float
    {
        return is_numeric($valor) ? (float) $valor : 0.0;
    }
}
