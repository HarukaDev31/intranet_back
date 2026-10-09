<?php

namespace App\Support\CargaConsolidada;

/**
 * Clasifica el texto libre de un concepto de costo resumen
 * (FOB / ISD / impuesto / logística). Logística = Servicio de importación
 * o cualquier concepto que diga logística (internacional, marítima, etc.).
 * Transferencia, flete y seguro no entran.
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
    public static function tipo($concepto, $formato = 'default')
    {
        $c = mb_strtolower(trim((string) $concepto));
        if ($c === '') {
            return self::OTRO;
        }

        if ($formato === 'bolivia') {
            return self::tipoBolivia($c);
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
     * Columna del listado de Bolivia a la que pertenece un concepto de la proforma
     * (null si no corresponde a ninguna). Fob, comisión de giro y logística van en USD;
     * impuestos, despacho y comisión Genuino van en Bs.
     *
     * @param mixed $concepto
     * @return string|null
     */
    public static function columnaBolivia($concepto)
    {
        $c = mb_strtolower(trim((string) $concepto));
        if ($c === '') {
            return null;
        }
        $tipo = self::tipoBolivia($c);
        if ($tipo === self::FOB) {
            return 'fob';
        }
        if ($tipo === self::LOGISTICA) {
            return 'logistica';
        }
        if ($tipo === self::IMPUESTO) {
            return 'impuesto';
        }
        if (strpos($c, 'giro') !== false || strpos($c, 'alibaba') !== false) {
            return 'comision_giro';
        }
        if (strpos($c, 'albo') !== false || strpos($c, 'despacho') !== false) {
            return 'despacho';
        }
        if (strpos($c, 'comisi') !== false && strpos($c, 'genuino') !== false) {
            return 'comision_genuino';
        }

        return null;
    }

    /**
     * Suma por columna Bolivia. Fob, comisión de giro y logística en USD (`valor`);
     * impuestos, despacho y comisión Genuino en Bs (`valor_bs`, o USD x tasa si falta).
     *
     * @param iterable<object|array<string, mixed>> $costos filas con concepto, valor, valor_bs, tasa_cambio
     * @return array<string, float>
     */
    public static function sumarColumnasBolivia($costos)
    {
        $cols = [
            'fob_usd' => 0.0,
            'comision_giro_usd' => 0.0,
            'logistica_usd' => 0.0,
            'impuesto_bs' => 0.0,
            'despacho_bs' => 0.0,
            'comision_genuino_bs' => 0.0,
        ];
        foreach ($costos as $costo) {
            $costo = (object) $costo;
            $col = self::columnaBolivia(isset($costo->concepto) ? $costo->concepto : '');
            if ($col === null) {
                continue;
            }
            $usd = (float) (isset($costo->valor) ? $costo->valor : 0);
            $tasa = (float) (isset($costo->tasa_cambio) ? $costo->tasa_cambio : 0);
            $bs = isset($costo->valor_bs)
                ? (float) $costo->valor_bs
                : ($tasa > 0 ? $usd * $tasa : 0.0);
            if ($col === 'fob' || $col === 'comision_giro' || $col === 'logistica') {
                $cols[$col . '_usd'] += $usd;
            } else {
                $cols[$col . '_bs'] += $bs;
            }
        }

        return array_map(function ($v) {
            return round($v, 2);
        }, $cols);
    }

    /**
     * Proforma Bolivia: solo cuentan el valor FOB, el transporte marítimo/terrestre (logística)
     * y los "Impuestos a la Aduana Nacional". Albo/despacho y comisiones no suman.
     *
     * @param string $c
     * @return string
     */
    private static function tipoBolivia($c)
    {
        if (self::esFob($c)) {
            return self::FOB;
        }
        if (strpos($c, 'transporte') !== false && (strpos($c, 'maritim') !== false || strpos($c, 'marítim') !== false || strpos($c, 'terrestre') !== false)) {
            return self::LOGISTICA;
        }
        if (strpos($c, 'impuest') !== false && strpos($c, 'aduana') !== false) {
            return self::IMPUESTO;
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
     * Servicio de importación, o logística + lo que sea (internacional, marítima…).
     * Flete, transferencia y seguro no entran.
     *
     * @param string $c
     * @return bool
     */
    private static function esLogistica($c)
    {
        if (strpos($c, 'servicio') !== false && strpos($c, 'import') !== false) {
            return true;
        }

        return strpos($c, 'logist') !== false || strpos($c, 'logíst') !== false;
    }
}
