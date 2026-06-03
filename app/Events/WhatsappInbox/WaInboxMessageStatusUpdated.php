<?php

namespace App\Events\WhatsappInbox;

use App\Support\WhatsApp\WaInboxQueue;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WaInboxMessageStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels, WaInboxQueue;

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
        return new PrivateChannel('whatsapp-inbox.coordinacion');
    }

    public function broadcastAs()
    {
        return 'WaInboxMessageStatusUpdated';
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
