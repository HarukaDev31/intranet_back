<?php

namespace App\Support\WhatsApp;

/**
 * Limpia cadenas para respuestas JSON (evita "Malformed UTF-8 characters").
 */
class WaJsonUtf8
{
    /**
     * @param  mixed  $data
     * @return mixed
     */
    public static function sanitize($data)
    {
        if (is_array($data)) {
            $out = [];
            foreach ($data as $key => $value) {
                $out[is_string($key) ? self::sanitizeString($key) : $key] = self::sanitize($value);
            }

            return $out;
        }

        if (is_string($data)) {
            return self::sanitizeString($data);
        }

        return $data;
    }

    /**
     * @param  mixed  $value
     * @return string
     */
    public static function sanitizeString($value)
    {
        if (!is_string($value) || $value === '') {
            return (string) $value;
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if ($clean !== false && $clean !== '') {
            return $clean;
        }

        foreach (['ISO-8859-1', 'CP1252', 'Windows-1252'] as $from) {
            $converted = @iconv($from, 'UTF-8//IGNORE', $value);
            if ($converted !== false && $converted !== '') {
                return $converted;
            }
        }

        $stripped = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return is_string($stripped) ? $stripped : '';
    }
}
