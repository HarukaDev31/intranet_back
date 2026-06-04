<?php

namespace App\Jobs\WaCopiloto;

use App\Services\WaCopiloto\WaCopilotoSendService;
use App\Support\WhatsApp\WaCopilotoJobContext;
use App\Support\WhatsApp\WaCopilotoLog;
use App\Traits\DatabaseConnectionTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWaCopilotoOutboundJob implements ShouldQueue
{
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

    /** @var int Segundos de espera antes de enviar (cadena batch 2, respeta orden en WhatsApp). */
    public $delayBeforeSendSeconds;

    public function __construct($messageId, ?string $domain = null, ?string $displayLabel = null, int $delayBeforeSendSeconds = 0)
    {
        $this->messageId = (int) $messageId;
        $this->domain = $domain;
        $this->displayLabel = $displayLabel;
        $this->delayBeforeSendSeconds = max(0, min($delayBeforeSendSeconds, 30));
        $this->onQueue((string) config('meta_whatsapp_copiloto.queue', 'notificaciones'));
    }

    public function displayName(): string
    {
        if ($this->displayLabel !== null && $this->displayLabel !== '') {
            return 'Inbox Meta: ' . $this->displayLabel;
        }

        return 'Inbox Meta · mensaje #' . $this->messageId;
    }

    public function handle(WaCopilotoSendService $sendService)
    {
        $this->setDatabaseConnection(
            WaCopilotoJobContext::resolveJobDomain($this->domain)
        );

        WaCopilotoLog::info('job.SendWaCopilotoOutbound.start', [
            'message_id' => $this->messageId,
            'queue' => $this->queue,
            'attempt' => $this->attempts(),
            'domain' => WaCopilotoJobContext::resolveJobDomain($this->domain),
        ]);

        if ($this->delayBeforeSendSeconds > 0) {
            sleep($this->delayBeforeSendSeconds);
        }

        try {
            $result = $sendService->sendOutboundMessage($this->messageId);
            if (empty($result['success'])) {
                WaCopilotoLog::warning('job.SendWaCopilotoOutbound.failed', [
                    'message_id' => $this->messageId,
                    'error' => isset($result['error']) ? $result['error'] : null,
                ]);
            } else {
                WaCopilotoLog::info('job.SendWaCopilotoOutbound.done', [
                    'message_id' => $this->messageId,
                ]);
            }
        } catch (\Exception $e) {
            WaCopilotoLog::error('job.SendWaCopilotoOutbound.exception', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            Log::error('SendWaCopilotoOutboundJob exception', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Exception $exception)
    {
        WaCopilotoLog::error('job.SendWaCopilotoOutbound.permanently_failed', [
            'message_id' => $this->messageId,
            'error' => $exception->getMessage(),
        ]);
    }
}
