<?php

namespace App\Support\Phone;

/**
 * Normaliza números de landing (Perú) a E.164 para CRM / Bitrix.
 */
final class PeruPhoneFormatter
{
    /**
     * Normaliza a E.164 tipo Perú (+51…). Pensado para WhatsApp de landings.
     *
     * Cubre: espacios/guiones, 0051…, 051…, 51 duplicado (5151…), y móvil nacional de 9 dígitos.
     */
    public static function toE164(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            return '';
        }

        // Doble prefijo país mal pegado (ej. +51 +51 987… en un solo string).
        if (strlen($digits) >= 12 && substr($digits, 0, 4) === '5151') {
            $digits = '51' . substr($digits, 4);
        }

        // Ya viene como 51 + celular (11 dígitos) o más largo con país 51.
        if (strlen($digits) >= 11 && substr($digits, 0, 2) === '51') {
            return '+' . $digits;
        }

        // Celular nacional sin país (9 dígitos; en Perú los móviles empiezan por 9).
        if (strlen($digits) === 9) {
            return '+51' . $digits;
        }

        // Fallback: otros largos (internacional sin “+” bien formado).
        return '+' . $digits;
    }
}
