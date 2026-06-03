<?php

namespace App\Jobs\WhatsappInbox;

use App\Services\WhatsappInbox\WhatsappInboxMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWaInboxInboundJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $webhookLogId;

    public function __construct($webhookLogId)
    {
        $this->webhookLogId = (int) $webhookLogId;
        $this->onQueue((string) config('meta_whatsapp.inbox_queue', 'notificaciones'));
    }

    public function handle(WhatsappInboxMessageService $messageService)
    {
        try {
            $messageService->processWebhookLog($this->webhookLogId);
        } catch (\Exception $e) {
            Log::error('ProcessWaInboxInboundJob failed', [
                'webhook_log_id' => $this->webhookLogId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
