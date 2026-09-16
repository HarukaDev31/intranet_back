<?php

namespace App\Events\SoporteTi;

use App\Models\SoporteTi\SoporteTiSolicitud;
use App\Support\SoporteTi\SoporteTiQueue;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se emite cuando un mensaje existente cambia (p. ej. adjuntos listos).
 */
class SoporteTiMensajeActualizado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels, SoporteTiQueue;

    public $chatUuid;
    public $codigo;
    public $mensaje;
    public $revisadosCount;

    public function __construct(SoporteTiSolicitud $solicitud, array $mensaje, $revisadosCount = null)
    {
        $this->chatUuid = $solicitud->salaChat ? $solicitud->salaChat->chat_uuid : null;
        $this->codigo = $solicitud->codigo;
        $this->mensaje = $mensaje;
        $this->revisadosCount = $revisadosCount;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('soporte-ti.chat.' . $this->chatUuid);
    }

    public function broadcastAs()
    {
        return 'SoporteTiMensajeActualizado';
    }

    public function broadcastWith()
    {
        $payload = array(
            'chat_uuid' => $this->chatUuid,
            'codigo' => $this->codigo,
            'mensaje' => $this->mensaje,
        );
        if ($this->revisadosCount !== null) {
            $payload['revisados_count'] = (int) $this->revisadosCount;
        }

        return $payload;
    }
}
