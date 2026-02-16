<?php

namespace App\Http\Controllers\BaseDatos;

use App\Http\Controllers\Controller;
use App\Services\BaseDatos\TramiteAduanaDocumentoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TramiteAduanaDocumentosController extends Controller
{
    protected $documentoService;

    public function __construct(TramiteAduanaDocumentoService $documentoService)
    {
        $this->documentoService = $documentoService;
    }

    public function index(int $idTramite): JsonResponse
    {
        $result = $this->documentoService->listarPorTramite($idTramite);
        if (!$result['success']) {
            return response()->json($result, 404);
        }
        return response()->json($result);
    }

    public function store(Request $request, int $idTramite): JsonResponse
    {
        $result = $this->documentoService->crear($request, $idTramite);
        if (!$result['success']) {
            return response()->json($result, 422);
        }
        return response()->json($result, 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $result = $this->documentoService->eliminar($id);
        if (!$result['success']) {
            $status = ($result['error'] ?? '') === 'Documento no encontrado' ? 404 : 500;
            return response()->json($result, $status);
        }
        return response()->json($result);
    }

    public function download(int $id)
    {
        $result = $this->documentoService->descargar($id);
        if (!$result['success']) {
            return response()->json($result, 404);
        }
        return response()->download($result['filePath'], $result['nombre_original']);
    }

    public function indexCategorias(int $idTramite): JsonResponse
    {
        $result = $this->documentoService->listarCategorias($idTramite);
        if (!$result['success']) {
            return response()->json($result, 404);
        }
        return response()->json($result);
    }

    public function storeCategoria(Request $request, int $idTramite): JsonResponse
    {
        $result = $this->documentoService->crearCategoria($request, $idTramite);
        if (!$result['success']) {
            return response()->json($result, 422);
        }
        return response()->json($result, 201);
    }
}
