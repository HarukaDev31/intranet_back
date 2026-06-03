<?php

namespace App\Jobs\WhatsappInbox;

use App\Services\WhatsappInbox\WhatsappInboxSendService;
use App\Support\WhatsApp\WaInboxJobContext;
use App\Support\WhatsApp\WaInboxLog;
use App\Traits\DatabaseConnectionTrait;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWaInboxOutboundJob implements ShouldQueue
{
    use Batchable;
    use DatabaseConnectionTrait;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $messageId;

    /** @var string|null */
    public $domain;

    /** @var string|null Etiqueta en Horizon (batch de envío). */
    public $displayLabel;

    public function __construct($messageId, ?string $domain = null, ?string $displayLabel = null)
    {
        $this->messageId = (int) $messageId;
        $this->domain = $domain;
        $this->displayLabel = $displayLabel;
        $this->onQueue((string) config('meta_whatsapp.inbox_queue', 'notificaciones'));
    }

    public function displayName(): string
    {
        if ($this->displayLabel !== null && $this->displayLabel !== '') {
            return 'Inbox Meta: ' . $this->displayLabel;
        }

        return 'Inbox Meta · mensaje #' . $this->messageId;
    }

    public function handle(WhatsappInboxSendService $sendService)
    {
        $this->setDatabaseConnection(
            WaInboxJobContext::resolveJobDomain($this->domain)
        );

        WaInboxLog::info('job.SendWaInboxOutbound.start', [
            'message_id' => $this->messageId,
            'queue' => $this->queue,
            'attempt' => $this->attempts(),
            'domain' => WaInboxJobContext::resolveJobDomain($this->domain),
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
