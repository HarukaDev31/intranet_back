<?php

namespace App\Support\WhatsApp;

use App\Models\Usuario;
use Illuminate\Broadcasting\PrivateChannel;

class WaInboxBroadcastChannel
{
    const LEGACY = 'whatsapp-inbox.coordinacion';

    /**
     * @param  int  $organizacionId
     * @return string
     */
    public static function name($organizacionId)
    {
        return 'whatsapp-inbox.org.' . (int) $organizacionId;
    }

    /**
     * @param  int  $organizacionId
     * @return array<int, PrivateChannel>
     */
    public static function channelsForOrganizacion($organizacionId)
    {
        $organizacionId = (int) $organizacionId;
        if ($organizacionId <= 0) {
            $organizacionId = Usuario::ID_ORGANIZACION_ADMIN;
        }

        $channels = [new PrivateChannel(self::name($organizacionId))];
        if ($organizacionId === Usuario::ID_ORGANIZACION_ADMIN) {
            $channels[] = new PrivateChannel(self::LEGACY);
        }

        return $channels;
    }
}
