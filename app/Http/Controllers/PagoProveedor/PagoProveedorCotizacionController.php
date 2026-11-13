<?php

namespace App\Http\Controllers\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Http\Controllers\Controller;
use App\Models\CalculadoraImportacion;
use App\Http\Resources\PagoProveedor\CotizacionResource;
use App\Http\Requests\PagoProveedor\ResumenPagoProveedorCotizacionRequest;
use App\Http\Requests\PagoProveedor\StorePagoProveedorCotizacionRequest;
use App\Http\Requests\PagoProveedor\UpdatePagoProveedorCotizacionRequest;
use App\Services\PagoProveedor\PagoProveedorAutorizacion;
use App\Services\PagoProveedor\PagoProveedorConfiguracionService;
use App\Services\PagoProveedor\PagoProveedorCotizacionCcLookup;
use App\Services\PagoProveedor\PagoProveedorCotizacionService;
use App\Services\PagoProveedor\PagoProveedorCotizacionWhatsappService;
use App\Services\PagoProveedor\PagoProveedorPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PagoProveedorCotizacionController extends Controller
{
    use RespondeConResource;

    public function __construct(
        private readonly PagoProveedorAutorizacion $autorizacion,
        private readonly PagoProveedorCotizacionService $cotizaciones,
        private readonly PagoProveedorCotizacionCcLookup $lookup,
        private readonly PagoProveedorPdfService $pdf,
        private readonly PagoProveedorConfiguracionService $configuracion,
        private readonly PagoProveedorCotizacionWhatsappService $whatsapp,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->autorizacion->exigirVentas($request->user());
            $pagina = $this->cotizaciones->listar([
                'search' => $request->get('search'),
                'estado' => $request->get('estado'),
                'consolidado' => $request->get('consolidado'),
                'resaltar_desde' => $request->get('resaltar_desde'),
                'per_page' => $request->get('per_page', 15),
            ]);

            return CotizacionResource::collection($pagina->items())->additional([
                'success' => true,
                'pagination' => [
                    'current_page' => $pagina->currentPage(),
                    'last_page' => $pagina->lastPage(),
                    'per_page' => $pagina->perPage(),
                    'total' => $pagina->total(),
                ],
                'consolidados' => $this->cotizaciones->consolidadosDisponibles(),
            ])->response();
        });
    }

    public function sugerirConsolidada(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->autorizacion->exigirVentas($request->user());
            $page = $this->lookup->search((string) $request->get('q', ''), (int) $request->get('per_page', 8));
            $items = [];
            foreach ($page->items() as $row) {
                if ($row instanceof CalculadoraImportacion) {
                    $items[] = $this->lookup->suggestion($row);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $items,
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
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
                'data' => $this->lookup->findByCode($codigo),
            ]);
        });
    }

    public function store(StorePagoProveedorCotizacionRequest $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validated();
            $cotizacion = $this->cotizaciones->crearConDatos($datos['cod_cotizacion_cc'], $datos, $actor);

            return $this->ok(CotizacionResource::detail($cotizacion), 201);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $this->autorizacion->exigirVentas($request->user());

            return $this->ok(CotizacionResource::detail($this->cotizaciones->ver($id)));
        });
    }

    public function update(UpdatePagoProveedorCotizacionRequest $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $cotizacion = $this->cotizaciones->actualizar($id, $request->validated(), $actor);

            return $this->ok(CotizacionResource::detail($cotizacion));
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

            return $this->ok(CotizacionResource::detail($cotizacion));
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

    public function enviarWhatsapp(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'message' => 'Resumen enviado por WhatsApp.',
                'data' => $this->cotizaciones->enviarWhatsapp($id, $actor),
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

    public function pdfResumen(ResumenPagoProveedorCotizacionRequest $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => $this->pdf->resumenBorrador($request->validated(), $actor),
            ]);
        });
    }

    public function vistaPreviaResumen(ResumenPagoProveedorCotizacionRequest $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => $this->whatsapp->vistaPreviaBorrador($request->validated()),
            ]);
        });
    }

    public function enviarResumen(ResumenPagoProveedorCotizacionRequest $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'message' => 'Resumen enviado por WhatsApp.',
                'data' => $this->whatsapp->enviarBorrador($request->validated(), $actor),
            ]);
        });
    }

    public function configuracion(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => $this->configuracion->all(),
            ]);
        });
    }

    public function actualizarVigencia(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate([
                'horas' => ['required', 'integer', 'min:1', 'max:240'],
                'tipo' => ['nullable', 'in:habiles,calendario'],
            ]);
            $this->configuracion->save('vigencia_cotizacion_horas', (int) $datos['horas']);
            if (isset($datos['tipo'])) {
                $this->configuracion->save('vigencia_cotizacion_tipo', $datos['tipo']);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'vigencia_cotizacion_horas' => (int) $datos['horas'],
                    'vigencia_cotizacion_tipo' => $this->configuracion->validityType(),
                ],
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
