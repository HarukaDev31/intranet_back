<?php

namespace App\Events;

use App\Models\Viatico;
use App\Models\Usuario;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ViaticoActualizado implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $viatico;
    public $usuarioAdministracion;
    public $usuarioCreador;
    public $message;
    public $queue = 'notificaciones';

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Viatico $viatico, Usuario $usuarioAdministracion, Usuario $usuarioCreador, string $message)
    {
        Log::info('ViaticoActualizado', [
            'viatico_id' => $viatico->id,
            'usuario_administracion_id' => $usuarioAdministracion->ID_Usuario,
            'usuario_creador_id' => $usuarioCreador->ID_Usuario,
            'message' => $message
        ]);

        $this->viatico = $viatico;
        $this->usuarioAdministracion = $usuarioAdministracion;
        $this->usuarioCreador = $usuarioCreador;
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Enviar al canal del usuario creador
        return [
            new PrivateChannel('App.Models.User.' . $this->usuarioCreador->ID_Usuario),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'viatico_id' => $this->viatico->id,
            'viatico_subject' => $this->viatico->subject,
            'viatico_total_amount' => $this->viatico->total_amount,
            'viatico_status' => $this->viatico->status,
            'usuario_administracion_id' => $this->usuarioAdministracion->ID_Usuario,
            'usuario_administracion_nombre' => $this->usuarioAdministracion->No_Nombres_Apellidos,
            'usuario_creador_id' => $this->usuarioCreador->ID_Usuario,
            'message' => $this->message,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get the broadcast event name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'ViaticoActualizado';
    }
}
