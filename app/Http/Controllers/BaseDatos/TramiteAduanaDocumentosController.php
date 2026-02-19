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

    /**
     * POST tramites/{idTramite}/documentos/batch
     * FormData: id_tipo_permiso[], archivo[], seccion[], id_categoria[], categoria[]
     */
    public function storeBatch(Request $request, int $idTramite): JsonResponse
    {
        $result = $this->documentoService->crearBatch($request, $idTramite);
        if (!$result['success']) {
            return response()->json($result, 422);
        }
        return response()->json($result, 201);
    }

    /**
     * POST tramites/{idTramite}/guardar-todo
     * FormData: archivos (como batch), f_caducidad (opcional), guardar_tipos (JSON array)
     */
    public function guardarTodo(Request $request, int $idTramite): JsonResponse
    {
        $result = $this->documentoService->guardarTodo($request, $idTramite);
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

    /**
     * POST tramites/{idTramite}/tipos-permiso/{idTipoPermiso}/guardar
     * Body: { documentos_tramite_ids: [], fotos_ids: [], seguimiento_ids: [] }
     */
    public function guardarTipoPermiso(Request $request, int $idTramite, int $idTipoPermiso): JsonResponse
    {
        $request->validate([
            'documentos_tramite_ids' => 'nullable|array',
            'documentos_tramite_ids.*' => 'integer',
            'fotos_ids' => 'nullable|array',
            'fotos_ids.*' => 'integer',
            'seguimiento_ids' => 'nullable|array',
            'seguimiento_ids.*' => 'integer',
        ]);

        $documentosTramiteIds = $request->input('documentos_tramite_ids', []);
        $fotosIds = $request->input('fotos_ids', []);
        $seguimientoIds = $request->input('seguimiento_ids', []);

        $result = $this->documentoService->guardarTipoPermiso(
            $idTramite,
            $idTipoPermiso,
            $documentosTramiteIds,
            $fotosIds,
            $seguimientoIds
        );

        if (!$result['success']) {
            $status = ($result['error'] ?? '') === 'Trámite no encontrado' ? 404 : 422;
            return response()->json($result, $status);
        }
        return response()->json($result);
    }

    /**
     * POST tramites/{idTramite}/tipos-permiso/{idTipoPermiso}/pago
     * Asigna un documento de pago_servicio (seleccionado en el modal) a la fila permiso.
     * Body: { id_documento (required), monto?, fecha_pago?, observacion? }
     */
    public function asignarPago(Request $request, int $idTramite, int $idTipoPermiso): JsonResponse
    {
        $request->validate([
            'id_documento' => 'required|integer',
            'monto'        => 'nullable|numeric',
            'fecha_pago'   => 'nullable|date',
            'observacion'  => 'nullable|string|max:500',
        ]);

        $result = $this->documentoService->asignarPagoServicio(
            $idTramite,
            $idTipoPermiso,
            (int) $request->input('id_documento'),
            $request->input('monto'),
            $request->input('fecha_pago'),
            $request->input('observacion')
        );

        if (!$result['success']) {
            $status = ($result['error'] ?? '') === 'Trámite no encontrado' ? 404 : 422;
            return response()->json($result, $status);
        }
        return response()->json($result);
    }
}
