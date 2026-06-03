<?php

namespace App\Jobs\WhatsappInbox;

use App\Services\WhatsappInbox\WhatsappInboxSendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWaInboxOutboundJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $messageId;

    public function __construct($messageId)
    {
        $this->messageId = (int) $messageId;
        $this->onQueue((string) config('meta_whatsapp.inbox_queue', 'notificaciones'));
    }

    public function handle(WhatsappInboxSendService $sendService)
    {
        $result = $sendService->sendOutboundMessage($this->messageId);
        if (empty($result['success'])) {
            Log::warning('SendWaInboxOutboundJob failed', [
                'message_id' => $this->messageId,
                'error' => isset($result['error']) ? $result['error'] : null,
            ]);
        }
    }
}
