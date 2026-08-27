<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso a Coordinación cuando el cliente guarda el Excel de confirmación.
 */
class ExcelConfirmacionClienteActualizado implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @var string */
    public $queue = 'notificaciones';

    /** @var array<string, mixed> */
    public $payload;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel('Coordinacion-notifications'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith()
    {
        return $this->payload;
    }

    /**
     * @return string
     */
    public function broadcastAs()
    {
        return 'ExcelConfirmacionClienteActualizado';
    }
}
