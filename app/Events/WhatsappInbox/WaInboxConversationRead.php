<?php

namespace App\Events\WhatsappInbox;

use App\Support\WhatsApp\WaInboxQueue;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WaInboxConversationRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels, WaInboxQueue;

    /** @var array<string, mixed> */
    public $conversation;

    /**
     * @param  array<string, mixed>  $conversation
     */
    public function __construct(array $conversation)
    {
        $this->conversation = $conversation;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('whatsapp-inbox.coordinacion');
    }

    public function broadcastAs()
    {
        return 'WaInboxConversationRead';
    }

    public function broadcastWith()
    {
        return [
            'conversation_id' => (int) ($this->conversation['id'] ?? 0),
            'conversation' => $this->conversation,
        ];
    }
}
