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

class ViaticoCreado implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $viatico;
    public $usuario;
    public $message;
    public $queue = 'notificaciones';

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Viatico $viatico, Usuario $usuario, string $message)
    {
        Log::info('ViaticoCreado', [
            'viatico_id' => $viatico->id,
            'usuario_id' => $usuario->ID_Usuario,
            'message' => $message
        ]);

        $this->viatico = $viatico;
        $this->usuario = $usuario;
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel('Administracion-notifications'),
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
            'usuario_id' => $this->usuario->ID_Usuario,
            'usuario_nombre' => $this->usuario->No_Nombres_Apellidos,
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
        return 'ViaticoCreado';
    }
}
