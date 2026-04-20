<?php

namespace App\Http\Controllers\BaseDatos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportUsuarioDatosFacturacionRequest;
use App\Http\Requests\RollbackUsuarioDatosFacturacionImportRequest;
use App\Services\BaseDatos\Clientes\UsuarioDatosFacturacionImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class UsuarioDatosFacturacionImportController extends Controller
{
    /** @var UsuarioDatosFacturacionImportService */
    protected $importService;

    public function __construct(UsuarioDatosFacturacionImportService $importService)
    {
        $this->importService = $importService;
    }

    public function importExcel(ImportUsuarioDatosFacturacionRequest $request)
    {
        try {
            $authUser = null;
            try {
                $authUser = JWTAuth::parseToken()->authenticate();
            } catch (\Exception $e) {
                $authUser = auth()->user();
            }

            $result = $this->importService->enqueueImport(
                $request->file('excel_file'),
                $authUser ? (int) $authUser->id : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Importacion encolada correctamente. El procesamiento se ejecutara en segundo plano.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('UsuarioDatosFacturacionImportController@importExcel', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo procesar la importacion: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function listImports(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 100);
            if ($limit <= 0) {
                $limit = 100;
            }
            if ($limit > 500) {
                $limit = 500;
            }

            $data = $this->importService->listImports($limit);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('UsuarioDatosFacturacionImportController@listImports', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener la lista de importaciones.',
            ], 500);
        }
    }

    public function rollbackImport(RollbackUsuarioDatosFacturacionImportRequest $request, $idImport)
    {
        try {
            $result = $this->importService->rollbackImport((int) $idImport);

            return response()->json([
                'success' => true,
                'message' => $result['already_rolled_back']
                    ? 'La importacion ya habia sido revertida.'
                    : 'Rollback ejecutado correctamente.',
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            Log::error('UsuarioDatosFacturacionImportController@rollbackImport', [
                'error' => $e->getMessage(),
                'id_import' => $idImport,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo revertir la importacion.',
            ], 500);
        }
    }
}

