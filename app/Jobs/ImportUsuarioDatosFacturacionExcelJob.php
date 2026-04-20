<?php

namespace App\Jobs;

use App\Services\BaseDatos\Clientes\UsuarioDatosFacturacionImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportUsuarioDatosFacturacionExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    protected $idImport;

    /**
     * @param int $idImport
     */
    public function __construct($idImport)
    {
        $this->idImport = (int) $idImport;
        $this->onQueue('importaciones_facturacion');
    }

    public function handle(UsuarioDatosFacturacionImportService $service)
    {
        try {
            $service->processImportById($this->idImport);
        } catch (\Throwable $e) {
            Log::error('ImportUsuarioDatosFacturacionExcelJob fallo', [
                'id_import' => $this->idImport,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
