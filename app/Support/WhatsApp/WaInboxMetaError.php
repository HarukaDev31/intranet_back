<?php

namespace App\Support\WhatsApp;

/**
 * Mensajes claros para errores Graph / media de plantillas WhatsApp Inbox.
 */
class WaInboxMetaError
{
    /**
     * @param  int  $httpStatus
     * @param  mixed  $json
     * @return string
     */
    public static function userMessage($httpStatus, $json)
    {
        $code = 0;
        $message = '';
        $details = '';

        if (is_array($json) && isset($json['error']) && is_array($json['error'])) {
            $err = $json['error'];
            $code = isset($err['code']) ? (int) $err['code'] : 0;
            $message = isset($err['message']) ? (string) $err['message'] : '';
            if (isset($err['error_data']['details'])) {
                $details = (string) $err['error_data']['details'];
            }
        }

        $haystack = strtolower($message . ' ' . $details);

        if ($code === 131053
            || strpos($haystack, 'audiocodec=opus') !== false
            || strpos($haystack, 'videocodec=unknown') !== false
            || strpos($haystack, 'choose a different file') !== false) {
            return 'El video no es compatible con WhatsApp (H.264 + audio AAC). '
                . 'El servidor intenta convertirlo automáticamente; si persiste, prueba otro archivo más corto.';
        }

        if ($code === 131052 || strpos($haystack, 'file too large') !== false) {
            return 'El archivo supera el tamaño máximo permitido por WhatsApp para plantillas.';
        }

        if ($code === 132012 || strpos($haystack, 'format mismatch') !== false) {
            return 'El archivo no coincide con el tipo exigido por la plantilla (PDF, imagen o video según corresponda).';
        }

        if ($code === 132000
            || $code === 132001
            || strpos($haystack, 'number of params') !== false
            || strpos($haystack, 'param count') !== false) {
            return 'La plantilla de Meta no coincide con los parámetros enviados (conteo o formato). '
                . 'Revisa en Business Manager que el body no tenga variables extra y vuelve a intentar el envío.';
        }

        $out = 'Meta API HTTP ' . $httpStatus;
        if ($message !== '') {
            $out .= ': ' . $message;
            if ($code > 0) {
                $out .= ' (#' . $code . ')';
            }
        }

        return $out;
    }
}
