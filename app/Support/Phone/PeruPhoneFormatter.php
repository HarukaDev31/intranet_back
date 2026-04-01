<?php

namespace App\Support\Phone;

/**
 * Normaliza números de landing (Perú) a E.164 para CRM / Bitrix.
 */
final class PeruPhoneFormatter
{
    public static function toE164(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';
        if ($digits === '') {
            return '';
        }
        if (substr($digits, 0, 2) === '51' && strlen($digits) >= 11) {
            return '+' . $digits;
        }
        if (strlen($digits) === 9) {
            return '+51' . $digits;
        }

        return '+' . $digits;
    }
}
