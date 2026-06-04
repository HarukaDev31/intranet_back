<?php

namespace App\Events\WaCopiloto;

use App\Support\WhatsApp\WaCopilotoQueue;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WaCopilotoMessageStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels, WaCopilotoQueue;

    public $conversationId;
    public $messageId;
    public $deliveryStatus;

    /** @var array<string, mixed>|null */
    public $message;

    /**
     * @param  array<string, mixed>|null  $message
     */
    public function __construct($conversationId, $messageId, $deliveryStatus, array $message = null)
    {
        $this->conversationId = (int) $conversationId;
        $this->messageId = (int) $messageId;
        $this->deliveryStatus = (string) $deliveryStatus;
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('whatsapp-copiloto.ventas');
    }

    public function broadcastAs()
    {
        return 'WaCopilotoMessageStatusUpdated';
    }

    public function broadcastWith()
    {
        return [
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
            'delivery_status' => $this->deliveryStatus,
            'message' => $this->message,
        ];
    }
}
