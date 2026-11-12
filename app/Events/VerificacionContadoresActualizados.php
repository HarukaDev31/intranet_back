<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Empuja contadores de verificación / viáticos / pago a proveedor.
 * El payload trae solo las claves que cambiaron (o el bloque completo).
 */
class VerificacionContadoresActualizados implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $counts;
    public $queue = 'notificaciones';

    /**
     * @param  array  $counts
     */
    public function __construct(array $counts)
    {
        $this->counts = $counts;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('Administracion-notifications'),
        ];
    }

    public function broadcastWith()
    {
        return [
            'counts' => $this->counts,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs()
    {
        return 'VerificacionContadoresActualizados';
    }
}
