<?php

namespace App\Http\Controllers\BaseDatos;

use App\Http\Controllers\Controller;
use App\Services\BaseDatos\TramiteAduanaCatalogoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TramiteAduanaCatalogosController extends Controller
{
    /** @var TramiteAduanaCatalogoService */
    protected $catalogoService;

    public function __construct(TramiteAduanaCatalogoService $catalogoService)
    {
        $this->catalogoService = $catalogoService;
    }

    /**
     * GET /api/base-datos/tramite-aduana-catalogos/entidades
     * Lista entidades activas (tabla tramite_aduana_entidades, excluye soft deleted).
     */
    public function getEntidades(Request $request): JsonResponse
    {
        $result = $this->catalogoService->listarEntidadesActivas();
        $status = $result['success'] ? 200 : 500;
        return response()->json($result, $status);
    }

    /**
     * GET /api/base-datos/tramite-aduana-catalogos/tipos-permiso
     * Lista tipos de permiso activos (tabla tramite_aduana_tipos_permiso, excluye soft deleted).
     */
    public function getTiposPermiso(Request $request): JsonResponse
    {
        $result = $this->catalogoService->listarTiposPermisoActivos();
        $status = $result['success'] ? 200 : 500;
        return response()->json($result, $status);
    }

    /**
     * POST /api/base-datos/tramite-aduana-catalogos/entidades
     * Crea entidad en tramite_aduana_entidades. Body: { "nombre": "string" }.
     */
    public function storeEntidad(Request $request): JsonResponse
    {
        $result = $this->catalogoService->crearEntidad($request);
        $status = $result['success'] ? 201 : 422;
        return response()->json($result, $status);
    }

    /**
     * POST /api/base-datos/tramite-aduana-catalogos/tipos-permiso
     * Crea tipo de permiso en tramite_aduana_tipos_permiso. Body: { "nombre_permiso": "string" }.
     */
    public function storeTipoPermiso(Request $request): JsonResponse
    {
        $result = $this->catalogoService->crearTipoPermiso($request);
        $status = $result['success'] ? 201 : 422;
        return response()->json($result, $status);
    }
}
