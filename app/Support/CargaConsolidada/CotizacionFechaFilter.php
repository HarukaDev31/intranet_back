<?php

namespace App\Support\CargaConsolidada;

/**
 * Filtro de rango sobre fecha de cotización (con fallback a created_at).
 */
class CotizacionFechaFilter
{
    /**
     * @param  mixed  $value
     * @return string|null Y-m-d
     */
    public static function parse($value)
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $fechaInicio
     * @param  mixed  $fechaFin
     * @param  string  $fechaColumn
     */
    public static function applyToQuery($query, $fechaInicio, $fechaFin, $fechaColumn = 'fecha')
    {
        $inicio = self::parse($fechaInicio);
        $fin = self::parse($fechaFin);
        if (!$inicio && !$fin) {
            return;
        }

        $expr = 'DATE(' . $fechaColumn . ')';
        if ($inicio) {
            $query->whereRaw($expr . ' >= ?', [$inicio]);
        }
        if ($fin) {
            $query->whereRaw($expr . ' <= ?', [$fin]);
        }
    }
}
