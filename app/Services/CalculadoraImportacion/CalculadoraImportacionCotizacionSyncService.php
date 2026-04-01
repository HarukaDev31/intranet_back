<?php

namespace App\Services\CalculadoraImportacion;

use App\Http\Controllers\CargaConsolidada\CotizacionController;
use App\Models\CalculadoraImportacion;
use App\Models\CalculadoraImportacionProveedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CalculadoraImportacionCotizacionSyncService
{
    private CalculadoraImportacionExcelService $excelService;

    public function __construct(CalculadoraImportacionExcelService $excelService)
    {
        $this->excelService = $excelService;
    }

    public function actualizarCotizacionDesdeCalculadora(CalculadoraImportacion $calculadora): bool
    {
        try {
            $fileUrl = $calculadora->url_cotizacion;
            $fileContents = $this->excelService->downloadFileFromUrl($fileUrl);

            if (!$fileContents) {
                Log::warning('Archivo Excel no encontrado, no se puede actualizar cotización', [
                    'calculadora_id' => $calculadora->id,
                    'url' => $fileUrl,
                ]);
                return false;
            }

            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $extension = pathinfo($fileUrl, PATHINFO_EXTENSION) ?: 'xlsx';
            $tempFileName = uniqid('calculadora_update_') . '.' . $extension;
            $tempFilePath = $tempPath . '/' . $tempFileName;
            file_put_contents($tempFilePath, $fileContents);

            $fileData = [
                'name' => basename($fileUrl),
                'type' => mime_content_type($tempFilePath),
                'tmp_name' => $tempFilePath,
                'error' => 0,
                'size' => filesize($tempFilePath),
            ];

            $cotizacionController = app(CotizacionController::class);
            $result = $cotizacionController->updateFromCalculadora($calculadora->id_cotizacion, $fileData);

            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }

            if ($result === "success") {
                $updateData = [
                    'id_usuario' => $calculadora->id_usuario,
                    'from_calculator' => true,
                ];
                if ($calculadora->url_cotizacion) {
                    $updateData['cotizacion_file_url'] = $calculadora->url_cotizacion;
                }
                Cotizacion::where('id', $calculadora->id_cotizacion)->update($updateData);

                // Importante: cuando cccp se crea/actualiza, reflejar su ID en calculadora_importacion_proveedores
                $this->sincronizarIdProveedorDesdeCotizacion($calculadora);

                Log::info('Cotización actualizada desde calculadora', [
                    'calculadora_id' => $calculadora->id,
                    'cotizacion_id' => $calculadora->id_cotizacion,
                ]);
                return true;
            }

            Log::error('Error al actualizar cotización desde calculadora', [
                'calculadora_id' => $calculadora->id,
                'cotizacion_id' => $calculadora->id_cotizacion,
                'result' => $result,
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Excepción al actualizar cotización: ' . $e->getMessage(), [
                'calculadora_id' => $calculadora->id,
            ]);
            return false;
        }
    }

    public function crearCotizacionDesdeCalculadoraExcel(CalculadoraImportacion $calculadora): void
    {
        $fileUrl = $calculadora->url_cotizacion;
        if (empty($fileUrl) || empty($calculadora->id_carga_consolidada_contenedor)) {
            return;
        }

        $fileContents = $this->excelService->downloadFileFromUrl($fileUrl);
        if (!$fileContents) {
            Log::error('No se pudo descargar el archivo de cotización', [
                'calculadora_id' => $calculadora->id,
                'url' => $fileUrl,
            ]);
            return;
        }

        $tempPath = storage_path('app/temp');
        if (!file_exists($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        $extension = pathinfo($fileUrl, PATHINFO_EXTENSION) ?: 'xlsx';
        $tempFileName = uniqid('calculadora_') . '.' . $extension;
        $tempFilePath = $tempPath . '/' . $tempFileName;
        file_put_contents($tempFilePath, $fileContents);

        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $tempFilePath,
            basename($fileUrl),
            mime_content_type($tempFilePath),
            null,
            true
        );

        $storeRequest = new Request();
        $storeRequest->merge(['id_contenedor' => $calculadora->id_carga_consolidada_contenedor]);
        $storeRequest->files->set('cotizacion', $uploadedFile);

        $currentUserId = auth()->id();

        $cotizacionController = app(CotizacionController::class);
        $response = $cotizacionController->storeFromCalculadora($storeRequest);
        $responseData = json_decode($response->getContent(), true);

        if (file_exists($tempFilePath)) {
            unlink($tempFilePath);
        }

        if (isset($responseData['id']) && ($responseData['status'] ?? null) === 'success') {
            $cotizacionId = $responseData['id'];

            Cotizacion::where('id', $cotizacionId)->update([
                'id_usuario' => $calculadora->id_usuario ?? $currentUserId,
                'from_calculator' => true,
                'cotizacion_file_url' => $calculadora->url_cotizacion,
                'es_imo' => (bool) ($calculadora->es_imo ?? false),
            ]);

            $calculadora->id_cotizacion = $cotizacionId;
            $calculadora->save();

            Log::info('Cotización creada desde calculadora via store()', [
                'calculadora_id' => $calculadora->id,
                'cotizacion_id' => $cotizacionId,
            ]);
        } else {
            Log::error('Error al crear cotización desde calculadora', [
                'calculadora_id' => $calculadora->id,
                'response' => $responseData,
            ]);
        }
    }

    /**
     * Sincroniza id_proveedor (cccp.id) y code_supplier desde cotización hacia
     * calculadora_importacion_proveedores, emparejando por code_supplier (no por orden de id).
     */
    private function sincronizarIdProveedorDesdeCotizacion(CalculadoraImportacion $calculadora): void
    {
        if (empty($calculadora->id_cotizacion)) {
            return;
        }

        $porCodigo = [];
        foreach (
            CotizacionProveedor::where('id_cotizacion', $calculadora->id_cotizacion)
                ->get(['id', 'code_supplier']) as $cp
        ) {
            $ck = trim((string) ($cp->code_supplier ?? ''));
            if ($ck !== '' && !isset($porCodigo[$ck])) {
                $porCodigo[$ck] = $cp;
            }
        }

        $sincronizados = 0;
        foreach (
            CalculadoraImportacionProveedor::where('id_calculadora_importacion', $calculadora->id)->get() as $provCalc
        ) {
            $ck = trim((string) ($provCalc->code_supplier ?? ''));
            if ($ck === '') {
                continue;
            }
            $provCot = $porCodigo[$ck] ?? null;
            if (!$provCot) {
                continue;
            }
            $provCalc->update([
                'id_proveedor' => $provCot->id,
                'code_supplier' => $provCot->code_supplier ?: $provCalc->code_supplier,
            ]);
            $sincronizados++;
        }

        Log::info('Sincronización id_proveedor calculadora <- cotización completada', [
            'calculadora_id' => $calculadora->id,
            'cotizacion_id' => $calculadora->id_cotizacion,
            'sincronizados' => $sincronizados,
        ]);
    }
}

