<?php

namespace App\Support\SoporteTi;

use App\Models\SoporteTi\SoporteTiSolicitud;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Canales Echo para notificaciones globales (sin suscribir cada sala).
 */
final class SoporteTiBroadcastChannels
{
    /**
     * @return array<int, PrivateChannel>
     */
    public static function forSolicitudNotificaciones(SoporteTiSolicitud $solicitud, $includeChatRoom = true)
    {
        $channels = array();

        $chatUuid = $solicitud->salaChat ? $solicitud->salaChat->chat_uuid : null;
        if ($includeChatRoom && $chatUuid) {
            $channels[] = new PrivateChannel('soporte-ti.chat.' . $chatUuid);
        }

        // Staff (PM / Soporte): un solo canal para todos los tickets
        $channels[] = new PrivateChannel('soporte-ti.staff');

        // Solicitante: canal personal (staff ya escucha en soporte-ti.staff)
        $solicitanteId = $solicitud->solicitante_user_id
            ? (int) $solicitud->solicitante_user_id
            : 0;
        if ($solicitanteId > 0) {
            $channels[] = new PrivateChannel('soporte-ti.user.' . $solicitanteId);
        }

        return $channels;
    }

    /**
     * @return array<int, int>
     */
    public static function participantUserIds(SoporteTiSolicitud $solicitud)
    {
        $ids = array();
        if ($solicitud->solicitante_user_id) {
            $ids[] = (int) $solicitud->solicitante_user_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }
}