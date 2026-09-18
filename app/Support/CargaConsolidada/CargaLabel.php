<?php

namespace App\Support\CargaConsolidada;

/**
 * `carga` en BD es el número (y `parte` si está partido).
 * Al mostrar: 15-2026 / 15A-2026. Sin prefijo de país.
 * El prefijo de org (3 letras) solo aplica a code_supplier.
 */
class CargaLabel
{
    /**
     * @param mixed $carga
     * @param mixed $fecha
     * @param mixed $parte
     * @param mixed $iso2 Ignorado. El ISO del país no forma parte del label.
     * @return string
     */
    public static function format($carga, $fecha = null, $parte = null, $iso2 = null)
    {
        $carga = self::sinPrefijoPais(trim((string) $carga));
        $parte = trim((string) $parte);
        $anio = $fecha ? (int) date('Y', strtotime((string) $fecha)) : 0;
        if ($anio <= 0) {
            $anio = (int) date('Y');
        }

        if ($carga !== '' && preg_match('/^\d+$/', $carga)) {
            return ((int) $carga) . $parte . '-' . $anio;
        }

        if ($carga !== '') {
            return $carga . $parte;
        }

        return $parte !== '' ? $parte . '-' . $anio : (string) $anio;
    }

    /**
     * Quita un ISO-2 inicial (PE-15-2026 → 15-2026). No toca labels tipo EC-SEED-01.
     *
     * @param mixed $carga
     * @return string
     */
    public static function sinPrefijoPais($carga)
    {
        $carga = trim((string) $carga);
        if ($carga === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]{2}-(\d.*)$/', $carga, $m)) {
            return $m[1];
        }

        return $carga;
    }

    /**
     * @param mixed $carga
     * @return string
     */
    public static function soloNumero($carga)
    {
        $digits = preg_replace('/\D/', '', (string) $carga);

        return $digits !== '' ? (string) ((int) $digits) : '';
    }
}
