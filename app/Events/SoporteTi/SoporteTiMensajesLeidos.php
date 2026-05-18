<?php

namespace App\Events\SoporteTi;

use App\Models\SoporteTi\SoporteTiSolicitud;
use App\Support\SoporteTi\SoporteTiQueue;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SoporteTiMensajesLeidos implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
    use SoporteTiQueue;

    public $chatUuid;
    public $codigo;
    public $lectorUsuarioId;
    public $mensajeIds;

    /**
     * @param SoporteTiSolicitud $solicitud
     * @param int                $lectorUsuarioId
     * @param int[]              $mensajeIds IDs de mensajes del autor que quedaron leídos
     */
    public function __construct(SoporteTiSolicitud $solicitud, $lectorUsuarioId, array $mensajeIds)
    {
        $this->chatUuid = $solicitud->salaChat ? $solicitud->salaChat->chat_uuid : null;
        $this->codigo = $solicitud->codigo;
        $this->lectorUsuarioId = (int) $lectorUsuarioId;
        $this->mensajeIds = array_values(array_map('intval', $mensajeIds));
    }

    public function broadcastOn()
    {
        return new PrivateChannel('soporte-ti.chat.' . $this->chatUuid);
    }

    public function broadcastAs()
    {
        return 'SoporteTiMensajesLeidos';
    }

    public function broadcastWith()
    {
        return array(
            'chat_uuid' => $this->chatUuid,
            'codigo' => $this->codigo,
            'lector_usuario_id' => $this->lectorUsuarioId,
            'mensaje_ids' => $this->mensajeIds,
        );
    }
}
