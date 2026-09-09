<?php

namespace App\Jobs;

use App\Events\FacturaComercialBatchFinished;
use App\Models\CargaConsolidada\ConsolidadoFacturaComercialBatch;
use App\Services\CargaConsolidada\Documentacion\FacturaComercialBatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateFacturaComercialJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $tries = 1;

    /** @var int */
    public $timeout = 900;

    /** @var bool */
    public $failOnTimeout = true;

    /** @var int */
    protected $batchId;

    public function __construct($batchId)
    {
        $this->batchId = (int) $batchId;
        $this->onQueue((string) config('carga_consolidada.queue', 'carga_consolidada'));
    }

    public function handle(FacturaComercialBatchService $service)
    {
        try {
            $service->processBatchById($this->batchId);
        } catch (\Throwable $e) {
            Log::error('GenerateFacturaComercialJob fallo', [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e)
    {
        $batch = ConsolidadoFacturaComercialBatch::find($this->batchId);
        if (!$batch || in_array($batch->estado, ['COMPLETED', 'FAILED'], true)) {
            return;
        }

        $batch->update([
            'fecha_fin' => now(),
            'estado' => 'FAILED',
            'mensaje_error' => $e->getMessage(),
        ]);

        event(new FacturaComercialBatchFinished(
            $batch->fresh(),
            'Error al generar la factura general: ' . $e->getMessage()
        ));
    }
}
