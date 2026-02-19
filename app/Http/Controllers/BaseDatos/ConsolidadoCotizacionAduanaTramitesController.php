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
     * Actualizar el estado de un tipo de permiso en el pivot
     */
    public function updateTipoPermisoEstado(Request $request, int $tramiteId, int $tipoPermisoId): JsonResponse
    {
        $request->validate([
            'estado' => 'required|string|in:PENDIENTE,SD,PAGADO,EN_TRAMITE,RECHAZADO,COMPLETADO',
        ]);

        $result = $this->tramiteAduanaService->actualizarEstadoTipoPermiso(
            $tramiteId,
            $tipoPermisoId,
            $request->estado
        );

        if (!$result['success']) {
            $status = (strpos($result['error'] ?? '', 'encontrado') !== false) ? 404 : 422;
            return response()->json($result, $status);
        }

        return response()->json($result);
    }

    /**
     * PATCH tramites/{id}/tipos-permiso/{idTipoPermiso}/fechas
     * Body: { f_inicio?: "Y-m-d", f_termino?: "Y-m-d" }. Calcula dias en el backend.
     */
    public function updateTipoPermisoFechas(Request $request, int $tramiteId, int $tipoPermisoId): JsonResponse
    {
        $request->validate([
            'f_inicio'  => 'nullable|date_format:Y-m-d',
            'f_termino' => 'nullable|date_format:Y-m-d',
        ]);

        $result = $this->tramiteAduanaService->actualizarFechasTipoPermiso(
            $tramiteId,
            $tipoPermisoId,
            $request->input('f_inicio'),
            $request->input('f_termino')
        );

        if (!$result['success']) {
            $status = (strpos($result['error'] ?? '', 'encontrado') !== false) ? 404 : 422;
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
