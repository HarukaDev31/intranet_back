<?php

namespace App\Events\SoporteTi;

use App\Models\SoporteTi\SoporteTiSolicitud;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SoporteTiMensajeCreado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chatUuid;
    public $codigo;
    public $mensaje;

    public function __construct(SoporteTiSolicitud $solicitud, array $mensaje)
    {
        $this->chatUuid = $solicitud->salaChat ? $solicitud->salaChat->chat_uuid : null;
        $this->codigo = $solicitud->codigo;
        $this->mensaje = $mensaje;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('soporte-ti.chat.' . $this->chatUuid);
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
