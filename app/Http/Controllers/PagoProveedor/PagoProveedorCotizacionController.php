<?php

namespace App\Http\Controllers\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PagoProveedor\StorePagoProveedorCotizacionRequest;
use App\Http\Requests\PagoProveedor\StorePagoProveedorProformaRequest;
use App\Http\Requests\PagoProveedor\UpdatePagoProveedorCotizacionRequest;
use App\Services\PagoProveedor\PagoProveedorAutorizacion;
use App\Services\PagoProveedor\PagoProveedorConfiguracionService;
use App\Services\PagoProveedor\PagoProveedorCotizacionCcLookup;
use App\Services\PagoProveedor\PagoProveedorCotizacionService;
use App\Services\PagoProveedor\PagoProveedorPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PagoProveedorCotizacionController extends Controller
{
    public function __construct(
        private readonly PagoProveedorAutorizacion $autorizacion,
        private readonly PagoProveedorCotizacionService $cotizaciones,
        private readonly PagoProveedorCotizacionCcLookup $lookup,
        private readonly PagoProveedorPdfService $pdf,
        private readonly PagoProveedorConfiguracionService $configuracion,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->autorizacion->exigirVentas($request->user());
            $pagina = $this->cotizaciones->listar([
                'search' => $request->get('search'),
                'estado' => $request->get('estado'),
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

    public function buscarConsolidada(string $codigo): JsonResponse
    {
        return $this->responder(function () use ($codigo) {
            $this->autorizacion->exigirVentas(request()->user());

            return response()->json([
                'success' => true,
                'data' => $this->lookup->buscar($codigo),
            ]);
        });
    }

    public function store(StorePagoProveedorCotizacionRequest $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $cotizacion = $this->cotizaciones->crear($request->validated()['cod_cotizacion_cc'], $actor);

            return response()->json(['success' => true, 'data' => $cotizacion], 201);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $this->autorizacion->exigirVentas($request->user());

            return response()->json(['success' => true, 'data' => $this->cotizaciones->ver($id)]);
        });
    }

    public function update(UpdatePagoProveedorCotizacionRequest $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $cotizacion = $this->cotizaciones->actualizar($id, $request->validated(), $actor);

            return response()->json(['success' => true, 'data' => $cotizacion]);
        });
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $this->cotizaciones->eliminar($id, $actor);

            return response()->json(['success' => true, 'message' => 'Cotización eliminada.']);
        });
    }

    public function renovar(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate([
                'tipo_cambio' => ['nullable', 'numeric', 'gt:0'],
            ]);
            $cotizacion = $this->cotizaciones->renovar($id, isset($datos['tipo_cambio']) ? (float) $datos['tipo_cambio'] : null, $actor);

            return response()->json(['success' => true, 'data' => $cotizacion]);
        });
    }

    public function proforma(StorePagoProveedorProformaRequest $request, int $id, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $id, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $cotizacion = $this->cotizaciones->subirProforma($id, $proveedorId, $request->file('proforma'), $actor);

            return response()->json(['success' => true, 'data' => $cotizacion]);
        });
    }

    public function whatsapp(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => $this->cotizaciones->vistaPreviaWhatsapp($id, $actor),
            ]);
        });
    }

    public function pdf(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => $this->pdf->resumen($id, $actor),
            ]);
        });
    }

    public function configuracion(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => $this->configuracion->todas(),
            ]);
        });
    }

    public function actualizarVigencia(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate([
                'horas' => ['required', 'integer', 'min:1', 'max:240'],
            ]);
            $this->configuracion->guardar('vigencia_cotizacion_horas', (int) $datos['horas']);

            return response()->json([
                'success' => true,
                'data' => ['vigencia_cotizacion_horas' => (int) $datos['horas']],
            ]);
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
            Log::error('Pago a proveedor: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo completar la operación.',
            ], 500);
        }
    }
}
