<?php

namespace App\Events\SoporteTi;

use App\Models\SoporteTi\SoporteTiSolicitud;
use App\Support\SoporteTi\SoporteTiBroadcastChannels;
use App\Support\SoporteTi\SoporteTiQueue;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SoporteTiMensajeCreado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels, SoporteTiQueue;

    public $chatUuid;
    public $codigo;
    public $mensaje;

    /** @var SoporteTiSolicitud */
    protected $solicitud;

    public function __construct(SoporteTiSolicitud $solicitud, array $mensaje)
    {
        $solicitud->loadMissing('salaChat');
        $this->solicitud = $solicitud;
        $this->chatUuid = $solicitud->salaChat ? $solicitud->salaChat->chat_uuid : null;
        $this->codigo = $solicitud->codigo;
        $this->mensaje = $mensaje;
    }

    public function broadcastOn()
    {
        return SoporteTiBroadcastChannels::forSolicitudNotificaciones($this->solicitud, true);
    }

    public function broadcastAs()
    {
        return 'SoporteTiMensajeCreado';
    }

    public function broadcastWith()
    {
        return array(
            'chat_uuid' => $this->chatUuid,
            'codigo' => $this->codigo,
            'mensaje' => $this->mensaje,
        );
    }
}
