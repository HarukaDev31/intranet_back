<?php

namespace App\Jobs;

use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcesarCorteSeguimientoDatosProveedorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int|null */
    public $idContenedor;

    /** @var int */
    public $timeout = 900;

    /**
     * @param int|null $idContenedor
     */
    public function __construct($idContenedor = null)
    {
        $this->idContenedor = $idContenedor !== null ? (int) $idContenedor : null;
        $this->onQueue((string) config('carga_consolidada.queue', 'carga_consolidada'));
    }

    /**
     * @param SeguimientoConsolidadoDriveService $service
     */
    public function handle(SeguimientoConsolidadoDriveService $service)
    {
        Log::info('[SeguimientoDrive] Job Corte DATOS PROVEEDOR iniciado', [
            'id_contenedor' => $this->idContenedor,
        ]);

        try {
            $service->procesarCorteDatosProveedor($this->idContenedor);
            Log::info('[SeguimientoDrive] Job Corte DATOS PROVEEDOR finalizado');
        } catch (\Throwable $e) {
            Log::error('[SeguimientoDrive] Job Corte DATOS PROVEEDOR error', [
                'id_contenedor' => $this->idContenedor,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
