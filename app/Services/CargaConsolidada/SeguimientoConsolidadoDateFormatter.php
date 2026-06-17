<?php

namespace App\Services\CargaConsolidada;

use Carbon\Carbon;

/**
 * Fechas del Excel seguimiento → Drive en hora Perú (America/Lima).
 *
 * - arrive_date / arrive_date_china: DATE (día calendario, sin corrimiento UTC).
 * - updated_at / tracking: TIMESTAMP UTC en BD (servidor us-east-2, app UTC).
 * - ultima_actualizacion (row_sync): guardado como hora Lima en texto.
 */
class SeguimientoConsolidadoDateFormatter
{
    public const LIMA = 'America/Lima';

    public static function displayTimezone(): string
    {
        $tz = (string) config('carga_consolidada.seguimiento_corte_timezone', self::LIMA);

        return $tz !== '' ? $tz : self::LIMA;
    }

    /**
     * Fecha calendario (arrive_date_china, arrive_date).
     */
    public static function formatCalendarDate($value, string $format = 'j-M'): string
    {
        if ($value === null || trim((string) $value) === '') {
            return '';
        }

        $value = trim((string) $value);
        $tz = self::displayTimezone();

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return Carbon::createFromFormat('Y-m-d', $value, $tz)->format($format);
            }

            return Carbon::parse($value, $tz)->format($format);
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Timestamp UTC desde BD (p. ej. tracking updated_at).
     *
     * @return Carbon|null
     */
    public static function parseUtcToLima($value)
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value, 'UTC')->timezone(self::displayTimezone());
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Timestamp UTC desde BD → texto para celda Excel.
     */
    public static function formatUtcTimestamp($value, string $format = 'd/m/Y H:i'): string
    {
        $lima = self::parseUtcToLima($value);

        return $lima ? $lima->format($format) : (string) $value;
    }

    /**
     * Valor ya persistido como hora Lima (contenedor_seguimiento_row_sync.ultima_actualizacion).
     */
    public static function formatLimaLocalTimestamp($value, string $format = 'd/m/Y H:i'): string
    {
        if ($value === null || trim((string) $value) === '') {
            return '';
        }

        try {
            return Carbon::parse((string) $value, self::displayTimezone())->format($format);
        } catch (\Exception $e) {
            return (string) $value;
        }
    }
}
