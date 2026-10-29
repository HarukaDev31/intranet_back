<?php

namespace App\Http\Controllers\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Http\Controllers\Controller;
use App\Services\PagoProveedor\PagoProveedorAutorizacion;
use App\Services\PagoProveedor\PagoProveedorEtapa1Service;
use App\Services\PagoProveedor\PagoProveedorSolicitudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PagoProveedorSolicitudController extends Controller
{
    public function __construct(
        private readonly PagoProveedorAutorizacion $autorizacion,
        private readonly PagoProveedorSolicitudService $solicitudes,
        private readonly PagoProveedorEtapa1Service $etapa1,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->autorizacion->exigirVentas($request->user());
            $pagina = $this->solicitudes->listar([
                'search' => $request->get('search'),
                'per_page' => $request->get('per_page', 15),
            ]);

            return response()->json([
                'success' => true,
                'data' => $pagina->items(),
                'pagination' => [
                    'current_page' => $pagina->currentPage(),
                    'last_page' => $pagina->lastPage(),
                    'per_page' => $pagina->perPage(),
                    'total' => $pagina->total(),
                ],
            ]);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $this->autorizacion->exigirVentas($request->user());

            return response()->json(['success' => true, 'data' => $this->etapa1->detalle($id)]);
        });
    }

    public function storeDesdeCotizacion(Request $request, int $cotizacionId): JsonResponse
    {
        return $this->responder(function () use ($request, $cotizacionId) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => $this->solicitudes->crearDesdeCotizacion($cotizacionId, $actor),
            ], 201);
        });
    }

    private function responder(callable $accion): JsonResponse
    {
        try {
            return $accion();
        } catch (PagoProveedorException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Pago a proveedor solicitud: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo completar la operación.',
            ], 500);
        }
    }
}
