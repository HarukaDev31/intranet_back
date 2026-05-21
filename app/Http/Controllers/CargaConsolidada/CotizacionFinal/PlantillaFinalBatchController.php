<?php

namespace App\Http\Controllers\CargaConsolidada\CotizacionFinal;

use App\Http\Controllers\Controller;
use App\Services\CargaConsolidada\CotizacionFinal\PlantillaFinalBatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlantillaFinalBatchController extends Controller
{
    /** @var PlantillaFinalBatchService */
    protected $batchService;

    public function __construct(PlantillaFinalBatchService $batchService)
    {
        $this->batchService = $batchService;
    }

    public function listByContenedor(Request $request, $idContenedor)
    {
        try {
            $limit = (int) $request->query('limit', 100);
            $data = $this->batchService->listByContenedor($idContenedor, $limit);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('PlantillaFinalBatchController@listByContenedor', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener el historial de plantillas finales.',
            ], 500);
        }
    }

    public function downloadPlantilla($id)
    {
        try {
            $batch = $this->batchService->findBatchOrFail($id);
            if (empty($batch->plantilla_url)) {
                return response()->json(['success' => false, 'message' => 'Plantilla no disponible.'], 404);
            }

            $path = $this->batchService->resolveStoragePath($batch->plantilla_url);
            $name = $batch->nombre_plantilla ?: ('plantilla_' . $batch->id . '.xlsx');

            return response()->download($path, $name);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function downloadZip($id)
    {
        try {
            $batch = $this->batchService->findBatchOrFail($id);
            if (empty($batch->zip_path)) {
                return response()->json(['success' => false, 'message' => 'ZIP no disponible.'], 404);
            }

            $path = $this->batchService->resolveStoragePath($batch->zip_path);
            $name = 'Boletas_' . $batch->id_contenedor . '_' . $batch->id . '.zip';

            return response()->download($path, $name);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }
}
