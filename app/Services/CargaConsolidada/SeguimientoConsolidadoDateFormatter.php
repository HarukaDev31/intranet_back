<?php

namespace App\Services\CargaConsolidada;

use Carbon\Carbon;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

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
     * Día calendario en Lima para arrive_date / arrive_date_china.
     *
     * - DATE `Y-m-d` o medianoche: se usa el día tal cual (sin corrimiento UTC).
     * - DATETIME/timestamp (servidor UTC / us-east-2): se convierte a America/Lima
     *   y se toma ese día.
     */
    public static function calendarDayYmd($value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            if ($value->format('H:i:s') === '00:00:00') {
                $ymd = $value->format('Y-m-d');

                return $ymd === '0000-00-00' ? null : $ymd;
            }

            try {
                return Carbon::parse($value, 'UTC')->timezone(self::displayTimezone())->format('Y-m-d');
            } catch (\Exception $e) {
                return $value->format('Y-m-d');
            }
        }

        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '' || in_array($text, ['0000-00-00', '0000-00-00 00:00:00'], true)) {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})$/', $text, $m)) {
            return $m[1];
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})/', $text, $m)) {
            if ($m[2] === '00:00:00') {
                return $m[1];
            }

            try {
                return Carbon::parse($text, 'UTC')->timezone(self::displayTimezone())->format('Y-m-d');
            } catch (\Exception $e) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * Fecha calendario (arrive_date_china, arrive_date).
     * Mismo formato que Cotizaciones / CONTACTAR (d/m/Y), no j-M.
     */
    public static function formatCalendarDate($value, string $format = 'd/m/Y'): string
    {
        $ymd = self::parseCellToYmd($value);
        if ($ymd === null) {
            return '';
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $ymd, self::displayTimezone())->format($format);
        } catch (\Exception $e) {
            return $ymd;
        }
    }

    /**
     * Serial Excel del día calendario (Y/m/d, mediodía) sin zona horaria USA.
     *
     * @return float|null
     */
    public static function calendarDateToExcelSerial($value)
    {
        $ymd = self::parseCellToYmd($value);
        if ($ymd === null) {
            return null;
        }

        try {
            [$year, $month, $day] = array_map('intval', explode('-', $ymd));
            if ($year < 1900 || $month < 1 || $day < 1) {
                return null;
            }

            if (method_exists(ExcelDate::class, 'formattedPHPToExcel')) {
                return ExcelDate::formattedPHPToExcel($year, $month, $day, 12, 0, 0);
            }

            $dt = \DateTime::createFromFormat('!Y-m-d', $ymd);

            return $dt ? ExcelDate::PHPToExcel($dt) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Valor de celda Drive/Excel / BD → Y-m-d (día Lima).
     */
    public static function parseCellToYmd($value): ?string
    {
        $fromCalendar = self::calendarDayYmd($value);
        if ($fromCalendar !== null) {
            return $fromCalendar;
        }

        if ($value === null) {
            return null;
        }

        if (is_numeric($value) && !preg_match('/^\d{8}$/', (string) $value)) {
            $number = (float) $value;
            if ($number > 20000 && $number < 80000) {
                try {
                    $dt = ExcelDate::excelToDateTimeObject($number);

                    return Carbon::instance($dt)->format('Y-m-d');
                } catch (\Exception $e) {
                    // seguir con parse de texto
                }
            }
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $text = strtr(mb_strtolower($text, 'UTF-8'), [
            'ene' => 'jan',
            'abr' => 'apr',
            'ago' => 'aug',
            'dic' => 'dec',
        ]);

        try {
            $dt = Carbon::createFromFormat('d/m/Y', $text, self::displayTimezone());
            if ($dt && $dt->format('d/m/Y') === $text) {
                return $dt->format('Y-m-d');
            }
        } catch (\Exception $e) {
            // fallback
        }

        try {
            return Carbon::parse($text, 'UTC')->timezone(self::displayTimezone())->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
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
