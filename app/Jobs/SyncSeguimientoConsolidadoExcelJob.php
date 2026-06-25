<?php

namespace App\Jobs;

use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;
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
        Log::info('[SeguimientoDrive] Job Sync iniciado', [
            'id_contenedor' => $this->idContenedor,
            'attempt' => $this->attempts(),
        ]);

        $result = $service->executeSync($this->idContenedor);
        if (empty($result['success'])) {
            Log::warning('[SeguimientoDrive] Job Sync falló', [
                'id_contenedor' => $this->idContenedor,
                'message' => $result['message'] ?? 'unknown',
            ]);

            $service->releaseSyncDebounce($this->idContenedor);

            return;
        }

        Log::info('[SeguimientoDrive] Job Sync finalizado OK', [
            'id_contenedor' => $this->idContenedor,
        ]);
    }

    /**
     * @param Throwable $e
     */
    public function failed(Throwable $e)
    {
        Log::error('[SeguimientoDrive] Job Sync failed definitivamente', [
            'id_contenedor' => $this->idContenedor,
            'error' => $e->getMessage(),
        ]);

        app(SeguimientoConsolidadoDriveService::class)->releaseSyncDebounce($this->idContenedor);
    }
}
