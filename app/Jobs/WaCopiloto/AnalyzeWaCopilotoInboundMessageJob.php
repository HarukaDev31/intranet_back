<?php

namespace App\Jobs\WaCopiloto;

use App\Services\WaCopiloto\WaCopilotoMessageAnalysisService;
use App\Support\WhatsApp\WaCopilotoJobContext;
use App\Support\WhatsApp\WaCopilotoLog;
use App\Traits\DatabaseConnectionTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeWaCopilotoInboundMessageJob implements ShouldQueue
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

    /** @var int */
    public $tries = 2;

    public function __construct($messageId, ?string $domain = null)
    {
        $this->messageId = (int) $messageId;
        $this->domain = $domain;
        $this->onQueue((string) config('meta_whatsapp_copiloto.analysis_queue', config('meta_whatsapp_copiloto.queue', 'notificaciones')));
    }

    public function handle(WaCopilotoMessageAnalysisService $analysisService)
    {
        if (!config('meta_whatsapp_copiloto.analysis_enabled', true)) {
            return;
        }

        $domain = WaCopilotoJobContext::resolveJobDomain($this->domain);
        $connection = $this->setDatabaseConnection($domain);

        WaCopilotoLog::info('analysis.job.start', [
            'message_id' => (int) $this->messageId,
            'job_domain' => $domain,
            'db_connection' => $connection,
            'queue' => (string) config('meta_whatsapp_copiloto.analysis_queue', 'notificaciones'),
        ]);

        try {
            $result = $analysisService->analyzeInboundMessage($this->messageId);

            WaCopilotoLog::info('analysis.job.done', [
                'message_id' => (int) $this->messageId,
                'job_domain' => $domain,
                'db_connection' => $connection,
                'saved' => is_array($result),
                'insights_count' => is_array($result) && isset($result['insights']) && is_array($result['insights'])
                    ? count($result['insights'])
                    : 0,
            ]);
        } catch (\Exception $e) {
            WaCopilotoLog::error('analysis.job.failed', [
                'message_id' => (int) $this->messageId,
                'job_domain' => $domain,
                'db_connection' => $connection,
                'error' => $e->getMessage(),
            ]);
            Log::error('AnalyzeWaCopilotoInboundMessageJob failed', [
                'message_id' => $this->messageId,
                'job_domain' => $domain,
                'db_connection' => $connection,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
