<?php

namespace App\Support\CargaConsolidada;

/**
 * Clasifica el texto libre de un concepto de costo resumen
 * (FOB / ISD / impuesto / logística = solo servicio de importación).
 */
class ResumenCostoClasificador
{
    const FOB = 'fob';
    const ISD = 'isd';
    const IMPUESTO = 'impuesto';
    const LOGISTICA = 'logistica';
    const OTRO = 'otro';

    /**
     * @param mixed $concepto
     * @return string
     */
    public static function tipo($concepto)
    {
        $c = mb_strtolower(trim((string) $concepto));
        if ($c === '') {
            return self::OTRO;
        }

        if (self::esIsd($c)) {
            return self::ISD;
        }
        if (self::esFob($c)) {
            return self::FOB;
        }
        if (self::esImpuesto($c)) {
            return self::IMPUESTO;
        }
        if (self::esLogistica($c)) {
            return self::LOGISTICA;
        }

        return self::OTRO;
    }

    /**
     * @param string $c
     * @return bool
     */
    private static function esIsd($c)
    {
        if (preg_match('/\bisd\b/', $c)) {
            return true;
        }
        if (strpos($c, 'salida de divisa') !== false) {
            return true;
        }

        return strpos($c, 'salida') !== false && strpos($c, 'divisa') !== false;
    }

    /**
     * @param string $c
     * @return bool
     */
    private static function esFob($c)
    {
        if (strpos($c, 'mercader') !== false || strpos($c, 'mercanc') !== false || strpos($c, 'fob') !== false) {
            return true;
        }

        return (bool) preg_match('/\bexw\b/', $c);
    }

    /**
     * @param string $c
     * @return bool
     */
    private static function esImpuesto($c)
    {
        return strpos($c, 'impuest') !== false
            || strpos($c, 'tribut') !== false
            || strpos($c, 'aduana') !== false;
    }

    /**
     * Logística del listado = solo servicio de importación.
     * Flete, transferencia, seguro y logística internacional no entran.
     *
     * @param string $c
     * @return bool
     */
    private static function esLogistica($c)
    {
        return strpos($c, 'servicio') !== false && strpos($c, 'import') !== false;
    }
}
