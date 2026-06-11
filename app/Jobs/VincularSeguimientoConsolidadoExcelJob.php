<?php

namespace App\Jobs;

use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
        Log::info('[SeguimientoDrive] Job Vincular iniciado', [
            'id_contenedor' => $this->idContenedor,
            'attempt' => $this->attempts(),
        ]);

        $result = $service->executeVincular($this->idContenedor);

        if (empty($result['success'])) {
            Log::warning('[SeguimientoDrive] Job Vincular terminó con error', [
                'id_contenedor' => $this->idContenedor,
                'message' => $result['message'] ?? 'unknown',
            ]);
        } else {
            Log::info('[SeguimientoDrive] Job Vincular finalizado OK', [
                'id_contenedor' => $this->idContenedor,
            ]);
        }
    }

    /**
     * @param \Throwable $e
     */
    public function failed(\Throwable $e)
    {
        Log::error('[SeguimientoDrive] Job Vincular falló definitivamente', [
            'id_contenedor' => $this->idContenedor,
            'error' => $e->getMessage(),
        ]);

        app(SeguimientoConsolidadoDriveService::class)->markLinkFailed(
            $this->idContenedor,
            $e->getMessage()
        );
    }
}
