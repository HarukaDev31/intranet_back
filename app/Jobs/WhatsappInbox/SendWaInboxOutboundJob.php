<?php

namespace App\Jobs\WhatsappInbox;

use App\Services\WhatsappInbox\WhatsappInboxSendService;
use App\Support\WhatsApp\WaInboxLog;
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
        WaInboxLog::info('job.SendWaInboxOutbound.start', [
            'message_id' => $this->messageId,
            'queue' => $this->queue,
            'attempt' => $this->attempts(),
        ]);

        try {
            $result = $sendService->sendOutboundMessage($this->messageId);
            if (empty($result['success'])) {
                WaInboxLog::warning('job.SendWaInboxOutbound.failed', [
                    'message_id' => $this->messageId,
                    'error' => isset($result['error']) ? $result['error'] : null,
                ]);
            } else {
                WaInboxLog::info('job.SendWaInboxOutbound.done', [
                    'message_id' => $this->messageId,
                ]);
            }
        } catch (\Exception $e) {
            WaInboxLog::error('job.SendWaInboxOutbound.exception', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            Log::error('SendWaInboxOutboundJob exception', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Exception $exception)
    {
        WaInboxLog::error('job.SendWaInboxOutbound.permanently_failed', [
            'message_id' => $this->messageId,
            'error' => $exception->getMessage(),
        ]);
    }
}
