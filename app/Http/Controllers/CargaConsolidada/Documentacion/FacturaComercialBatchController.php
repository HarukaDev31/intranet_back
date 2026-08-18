<?php

namespace App\Http\Controllers\CargaConsolidada\Documentacion;

use App\Http\Controllers\Controller;
use App\Services\CargaConsolidada\Documentacion\FacturaComercialBatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class FacturaComercialBatchController extends Controller
{
    /** @var FacturaComercialBatchService */
    protected $batchService;

    public function __construct(FacturaComercialBatchService $batchService)
    {
        $this->batchService = $batchService;
    }

    public function enqueue(Request $request, $idContenedor)
    {
        try {
            $authUser = null;
            try {
                $authUser = JWTAuth::parseToken()->authenticate();
            } catch (\Exception $e) {
                $authUser = auth()->user();
            }

            $result = $this->batchService->enqueue(
                (int) $idContenedor,
                $authUser ? (int) $authUser->getIdUsuario() : null
            );

            $alreadyQueued = !empty($result['already_queued']);

            return response()->json([
                'success' => true,
                'message' => $alreadyQueued
                    ? 'Ya hay una generación en curso para este consolidado. Se notificará cuando termine.'
                    : 'Generación encolada. Se notificará cuando finalice.',
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('FacturaComercialBatchController@enqueue', [
                'id_contenedor' => $idContenedor,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo encolar la generación de la factura general.',
            ], 500);
        }
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
            Log::error('FacturaComercialBatchController@listByContenedor', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener el historial de facturas generales.',
            ], 500);
        }
    }

    public function download($id)
    {
        try {
            $batch = $this->batchService->findBatchOrFail($id);
            if (empty($batch->file_path) || strtoupper((string) $batch->estado) !== 'COMPLETED') {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo aún no está disponible.',
                ], 404);
            }

            $path = $this->batchService->resolveStoragePath($batch->file_path);
            $name = $batch->nombre_archivo ?: ('factura_procesada_' . $batch->id_contenedor . '.xlsx');

            return response()->download($path, $name);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
