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
     * Fecha calendario (arrive_date_china, arrive_date).
     * Mismo formato que Cotizaciones / CONTACTAR (d/m/Y), no j-M.
     */
    public static function formatCalendarDate($value, string $format = 'd/m/Y'): string
    {
        $ymd = self::parseCellToYmd($value);
        if ($ymd === null) {
            return '';
        }

        $tz = self::displayTimezone();

        try {
            return Carbon::createFromFormat('Y-m-d', $ymd, $tz)->format($format);
        } catch (\Exception $e) {
            return $ymd;
        }
    }

    /**
     * Serial Excel para celda de fecha calendario (sin hora / sin corrimiento UTC).
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
            $dt = Carbon::createFromFormat('Y-m-d', $ymd, self::displayTimezone())->startOfDay();

            return ExcelDate::PHPToExcel($dt);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Valor de celda Drive/Excel → Y-m-d (serial, DateTime, d/m/Y, Y-m-d, j-M).
     */
    public static function parseCellToYmd($value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::parse($value)->format('Y-m-d');
        }

        if ($value === null) {
            return null;
        }

        if (is_numeric($value) && !preg_match('/^\d{8}$/', (string) $value)) {
            $number = (float) $value;
            if ($number > 20000 && $number < 80000) {
                try {
                    return Carbon::instance(ExcelDate::excelToDateTimeObject($number))->format('Y-m-d');
                } catch (\Exception $e) {
                    // seguir con parse de texto
                }
            }
        }

        $text = trim((string) $value);
        if ($text === '' || in_array($text, ['0000-00-00', '0000-00-00 00:00:00'], true)) {
            return null;
        }

        $text = strtr(mb_strtolower($text, 'UTF-8'), [
            'ene' => 'jan',
            'abr' => 'apr',
            'ago' => 'aug',
            'dic' => 'dec',
        ]);

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $text)) {
            return ProveedorArriveDateHistoryService::normalizeDate($text);
        }

        try {
            $dt = Carbon::createFromFormat('d/m/Y', $text, self::displayTimezone());
            if ($dt && $dt->format('d/m/Y') === $text) {
                return $dt->format('Y-m-d');
            }
        } catch (\Exception $e) {
            // fallback
        }

        try {
            return Carbon::parse($text, self::displayTimezone())->format('Y-m-d');
        } catch (\Exception $e) {
            return ProveedorArriveDateHistoryService::normalizeDate($text);
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
