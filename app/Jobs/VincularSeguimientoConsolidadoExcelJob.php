<?php

namespace App\Jobs;

use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;
use App\Services\CargaConsolidada\SeguimientoConsolidadoFlowContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class VincularSeguimientoConsolidadoExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $idContenedor;

    /** @var int */
    public $tries = 2;

    /** @var int */
    public $timeout = 600;

    /**
     * @param int $idContenedor
     */
    public function __construct($idContenedor)
    {
        $this->idContenedor = (int) $idContenedor;
        $this->onQueue((string) config('carga_consolidada.queue', 'carga_consolidada'));
    }

    /**
     * @param SeguimientoConsolidadoDriveService $service
     */
    public function handle(SeguimientoConsolidadoDriveService $service)
    {
        $flow = new SeguimientoConsolidadoFlowContext($this->idContenedor, 'vincular_job');

        Log::info('[SeguimientoDrive] Job Vincular iniciado', array_merge($flow->baseContext(), [
            'step' => 'job_vincular_inicio',
            'attempt' => $this->attempts(),
            'job_id' => $this->job ? $this->job->getJobId() : null,
            'queue' => $this->queue,
        ]));

        try {
            $result = $service->executeVincular($this->idContenedor);

            if (empty($result['success'])) {
                Log::warning('[SeguimientoDrive] Job Vincular terminó con error', array_merge($flow->baseContext(), [
                    'step' => 'job_vincular_fallido',
                    'message' => $result['message'] ?? 'unknown',
                    'duration_ms' => $flow->elapsedMs(),
                ]));

                return;
            }

            Log::info('[SeguimientoDrive] Job Vincular finalizado OK', array_merge($flow->baseContext(), [
                'step' => 'job_vincular_ok',
                'drive_link' => $result['data']['drive_link'] ?? null,
                'file_name' => $result['data']['file_name'] ?? null,
                'duration_ms' => $flow->elapsedMs(),
            ]));
        } catch (Throwable $e) {
            Log::error('[SeguimientoDrive] Job Vincular excepción', array_merge($flow->baseContext(), [
                'step' => 'job_vincular_excepcion',
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => $flow->elapsedMs(),
            ]));

            throw $e;
        }
    }

    /**
     * @param Throwable $e
     */
    public function failed(Throwable $e)
    {
        Log::error('[SeguimientoDrive] Job Vincular falló definitivamente', [
            'flow' => 'seguimiento_drive',
            'step' => 'job_vincular_failed_definitivo',
            'id_contenedor' => $this->idContenedor,
            'attempts' => $this->attempts(),
            'error' => $e->getMessage(),
            'exception_class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        app(SeguimientoConsolidadoDriveService::class)->markLinkFailed(
            $this->idContenedor,
            $e->getMessage()
        );
    }
}
