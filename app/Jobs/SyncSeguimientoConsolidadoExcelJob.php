<?php

namespace App\Jobs;

use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;
use App\Services\CargaConsolidada\SeguimientoConsolidadoFlowContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncSeguimientoConsolidadoExcelJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $idContenedor;

    /** @var int */
    public $tries = 2;

    /** @var int */
    public $timeout = 600;

    /** @var int Segundos: lock único en Redis mientras el job existe en cola/ejecución. */
    public $uniqueFor = 900;

    /**
     * @param int $idContenedor
     */
    public function __construct($idContenedor)
    {
        $this->idContenedor = (int) $idContenedor;
        $this->onQueue((string) config('carga_consolidada.queue', 'carga_consolidada'));
    }

    /**
     * @return string
     */
    public function uniqueId()
    {
        return 'sync-seguimiento-drive-' . $this->idContenedor;
    }

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware()
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->releaseAfter(120)
                ->expireAfter(900),
        ];
    }

    /**
     * @param SeguimientoConsolidadoDriveService $service
     */
    public function handle(SeguimientoConsolidadoDriveService $service)
    {
        $flow = new SeguimientoConsolidadoFlowContext($this->idContenedor, 'sync_job');

        Log::info('[SeguimientoDrive] Job Sync iniciado', array_merge($flow->baseContext(), [
            'step' => 'job_sync_inicio',
            'attempt' => $this->attempts(),
            'job_id' => $this->job ? $this->job->getJobId() : null,
            'queue' => $this->queue,
        ]));

        $ok = false;
        $result = [];

        try {
            $result = $service->executeSync($this->idContenedor, null, $flow);
            $ok = !empty($result['success']);

            if (!$ok) {
                Log::warning('[SeguimientoDrive] Job Sync falló', array_merge($flow->baseContext(), [
                    'step' => 'job_sync_fallido',
                    'message' => $result['message'] ?? 'unknown',
                    'duration_ms' => $flow->elapsedMs(),
                ]));

                return;
            }

            Log::info('[SeguimientoDrive] Job Sync finalizado OK', array_merge($flow->baseContext(), [
                'step' => 'job_sync_ok',
                'file_name' => $result['file_name'] ?? null,
                'file_id' => $result['file_id'] ?? null,
                'duration_ms' => $flow->elapsedMs(),
            ]));
        } catch (Throwable $e) {
            Log::error('[SeguimientoDrive] Job Sync excepción', array_merge($flow->baseContext(), [
                'step' => 'job_sync_excepcion',
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => $flow->elapsedMs(),
            ]));

            throw $e;
        } finally {
            $service->releaseSyncDebounce($this->idContenedor);

            Log::info('[SeguimientoDrive] Job Sync debounce liberado', array_merge($flow->baseContext(), [
                'step' => 'job_sync_debounce_liberado',
            ]));

            $requeued = $service->requeueIfDirty(
                $this->idContenedor,
                $ok ? 'dirty_retry' : 'dirty_after_fail'
            );

            Log::info('[SeguimientoDrive] Job Sync finally', array_merge($flow->baseContext(), [
                'step' => 'job_sync_finally',
                'ok' => $ok,
                'requeued' => $requeued,
                'duration_ms' => $flow->elapsedMs(),
            ]));
        }
    }

    /**
     * @param Throwable $e
     */
    public function failed(Throwable $e)
    {
        Log::error('[SeguimientoDrive] Job Sync failed definitivamente', [
            'flow' => 'seguimiento_drive',
            'step' => 'job_sync_failed_definitivo',
            'id_contenedor' => $this->idContenedor,
            'attempts' => $this->attempts(),
            'error' => $e->getMessage(),
            'exception_class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        app(SeguimientoConsolidadoDriveService::class)->releaseSyncDebounce($this->idContenedor);
    }
}
