<?php

namespace App\Support\WhatsApp;

/**
 * Mensajes claros para errores Graph / media de plantillas WhatsApp Copiloto.
 */
class WaCopilotoMetaError
{
    /**
     * @param  int  $httpStatus
     * @param  mixed  $json
     * @return string
     */
    public static function userMessage($httpStatus, $json)
    {
        $code = 0;
        if (is_array($json) && isset($json['error']) && is_array($json['error'])) {
            $code = isset($json['error']['code']) ? (int) $json['error']['code'] : 0;
        }

        if ($httpStatus === 401 || $code === 190) {
            return 'Token Meta inválido o expirado para Copiloto. '
                . 'Revise META_WHATSAPP_ACCESS_TOKEN y que tenga permiso sobre el PHONE_NUMBER_ID '
                . config('meta_whatsapp_copiloto.phone_number_id') . ' (whatsapp_business_messaging).';
        }

        return WaInboxMetaError::userMessage($httpStatus, $json);
    }
}
