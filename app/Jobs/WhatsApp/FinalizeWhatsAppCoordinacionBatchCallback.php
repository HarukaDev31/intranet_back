<?php

namespace App\Jobs\WhatsApp;

use App\Services\WhatsApp\WhatsAppCoordinacionBatchService;
use Illuminate\Bus\Batch;

/**
 * Callback serializable para Bus::batch()->finally() (Horizon / job_batches).
 */
class FinalizeWhatsAppCoordinacionBatchCallback
{
    /** @var int */
    public $domainBatchId;

    public function __construct(int $domainBatchId)
    {
        $this->domainBatchId = $domainBatchId;
    }

    public function __invoke(Batch $laravelBatch): void
    {
        app(WhatsAppCoordinacionBatchService::class)
            ->finalizeFromLaravelBatch($this->domainBatchId, $laravelBatch);
    }
}
