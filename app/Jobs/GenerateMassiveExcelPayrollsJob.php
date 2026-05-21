<?php

namespace App\Jobs;

use App\Services\CargaConsolidada\CotizacionFinal\PlantillaFinalBatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMassiveExcelPayrollsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    protected $batchId;

    public function __construct($batchId)
    {
        $this->batchId = (int) $batchId;
        $this->onQueue('plantillas_finales');
    }

    public function handle(PlantillaFinalBatchService $service)
    {
        try {
            $service->processBatchById($this->batchId);
        } catch (\Throwable $e) {
            Log::error('GenerateMassiveExcelPayrollsJob fallo', [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
