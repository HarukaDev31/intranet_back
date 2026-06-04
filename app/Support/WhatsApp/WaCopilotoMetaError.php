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
        return WaInboxMetaError::userMessage($httpStatus, $json);
    }
}
