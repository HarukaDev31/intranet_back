<?php

namespace App\Events\SoporteTi;

use App\Support\SoporteTi\SoporteTiQueue;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa a PM / Soporte cuando se crea una solicitud (no están suscritos a la sala aún).
 */
class SoporteTiSolicitudCreada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels, SoporteTiQueue;

    /** @var array */
    public $payload;

    /**
     * @param array $solicitudMapped respuesta de mapSolicitud (snake_case API)
     */
    public function __construct(array $solicitudMapped)
    {
        $this->payload = array(
            'solicitud' => $solicitudMapped,
        );
    }

    public function broadcastOn()
    {
        return new PrivateChannel('soporte-ti.staff');
    }

    public function broadcastAs()
    {
        return 'SoporteTiSolicitudCreada';
    }

    public function broadcastWith()
    {
        return $this->payload;
    }
}
