<?php

namespace App\Http\Controllers\BaseDatos;

use App\Http\Controllers\Controller;
use App\Services\BaseDatos\TramiteAduanaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ConsolidadoCotizacionAduanaTramitesController extends Controller
{
    protected $tramiteAduanaService;

    public function __construct(TramiteAduanaService $tramiteAduanaService)
    {
        $this->tramiteAduanaService = $tramiteAduanaService;
    }

    /**
     * Listar trámites (consolidado cotizacion aduana)
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->tramiteAduanaService->listar($request);
        if (!$result['success']) {
            return response()->json($result, 500);
        }
        return response()->json($result);
    }

    /**
     * Mostrar un trámite por ID
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->tramiteAduanaService->mostrar($id);
        if (!$result['success']) {
            $status = ($result['data'] ?? null) === null ? 404 : 500;
            return response()->json($result, $status);
        }
        return response()->json($result);
    }

    /**
     * Crear trámite
     */
    public function store(Request $request): JsonResponse
    {
        $result = $this->tramiteAduanaService->crear($request);
        if (!$result['success']) {
            return response()->json($result, 422);
        }
        return response()->json($result, 201);
    }

    /**
     * Actualizar trámite
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $result = $this->tramiteAduanaService->actualizar($id, $request);
        if (!$result['success']) {
            $status = ($result['data'] ?? null) === null ? 404 : 422;
            return response()->json($result, $status);
        }
        return response()->json($result);
    }

    /**
     * Eliminar trámite
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->tramiteAduanaService->eliminar($id);
        if (!$result['success']) {
            $status = ($result['error'] ?? '') === 'Trámite no encontrado' ? 404 : 500;
            return response()->json($result, $status);
        }
        return response()->json($result);
    }
}
