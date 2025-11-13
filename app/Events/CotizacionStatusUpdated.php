<?php

namespace App\Events;

use App\Models\CargaConsolidada\Cotizacion;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CotizacionStatusUpdated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $cotizacion;
    public $status;
    public $message;
    public $queue = 'notificaciones';

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Cotizacion $cotizacion, string $status, string $message)
    {
        Log::info('CotizacionStatusUpdated', ['cotizacion' => $cotizacion, 'status' => $status, 'message' => $message]);

        $this->cotizacion = $cotizacion;
        $this->status = $status;
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // El prefijo 'private-' se agrega automáticamente por Laravel
        return new PrivateChannel('Cotizador-notifications');
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'id' => $this->cotizacion->id,
            'status' => $this->status,
            'message' => $this->message,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
