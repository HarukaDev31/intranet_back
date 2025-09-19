<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Traits\WhatsappTrait;
use Dompdf\Dompdf;
use Dompdf\Options;
use ZipArchive;
use Exception;

class SendRotuladoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait;

    protected $idCotizacion;
    protected $proveedoresIds;
    protected $idContainer;
    protected $userId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($idCotizacion, $proveedoresIds, $idContainer, $userId)
    {
        $this->idCotizacion = $idCotizacion;
        $this->proveedoresIds = $proveedoresIds;
        $this->idContainer = $idContainer;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Log::info("Iniciando SendRotuladoJob", [
                'id_cotizacion' => $this->idCotizacion,
                'proveedores_ids' => $this->proveedoresIds,
                'id_container' => $this->idContainer,
                'user_id' => $this->userId
            ]);

            // Importar el controlador para usar su lógica
            $controller = new \App\Http\Controllers\CargaConsolidada\CotizacionProveedorController();
            
            // Llamar al método que ya funciona
            $result = $controller->forceSendRotuladoMessages(
                $this->idCotizacion,
                null, // No necesitamos idProveedor específico
                $this->proveedoresIds,
                $this->idContainer
            );

            Log::info("SendRotuladoJob completado exitosamente", [
                'result' => 'success'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en SendRotuladoJob: ' . $e->getMessage(), [
                'id_cotizacion' => $this->idCotizacion,
                'proveedores_ids' => $this->proveedoresIds,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-lanzar la excepción para que Laravel maneje el fallo del job
            throw $e;
        }
    }
}
