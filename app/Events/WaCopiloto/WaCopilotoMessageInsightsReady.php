<?php

namespace App\Events\WaCopiloto;

use App\Support\WhatsApp\WaCopilotoQueue;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WaCopilotoMessageInsightsReady implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels, WaCopilotoQueue;

    /** @var array<string, mixed> */
    public $payload;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('whatsapp-copiloto.ventas');
    }

    public function broadcastAs()
    {
        return 'WaCopilotoMessageInsightsReady';
    }

    public function broadcastWith()
    {
        return $this->payload;
    }
}
