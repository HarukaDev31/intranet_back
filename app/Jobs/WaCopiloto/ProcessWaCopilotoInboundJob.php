<?php

namespace App\Jobs\WaCopiloto;

use App\Services\WaCopiloto\WaCopilotoMessageService;
use App\Support\WhatsApp\WaCopilotoJobContext;
use App\Traits\DatabaseConnectionTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWaCopilotoInboundJob implements ShouldQueue
{
    use DatabaseConnectionTrait;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $webhookLogId;

    /** @var string|null */
    public $domain;

    public function __construct($webhookLogId, ?string $domain = null)
    {
        $this->webhookLogId = (int) $webhookLogId;
        $this->domain = $domain;
        $this->onQueue((string) config('meta_whatsapp_copiloto.queue', 'notificaciones'));
    }

    public function handle(WaCopilotoMessageService $messageService)
    {
        $domain = WaCopilotoJobContext::resolveJobDomain($this->domain);
        $connection = $this->setDatabaseConnection($domain);

        try {
            $messageService->processWebhookLog($this->webhookLogId);
        } catch (\Exception $e) {
            Log::error('ProcessWaCopilotoInboundJob failed', [
                'webhook_log_id' => $this->webhookLogId,
                'job_domain' => $domain,
                'db_connection' => $connection,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
