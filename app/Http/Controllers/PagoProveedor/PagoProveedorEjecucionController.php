<?php

namespace App\Http\Controllers\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PagoProveedor\EjecucionPagoResource;
use App\Services\PagoProveedor\PagoProveedorAutorizacion;
use App\Services\PagoProveedor\PagoProveedorEjecucionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PagoProveedorEjecucionController extends Controller
{
    use RespondeConResource;

    public function __construct(
        private readonly PagoProveedorAutorizacion $authorization,
        private readonly PagoProveedorEjecucionService $executions,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->authorization->requireAdministration($request->user());
            $filters = $request->all();
            $page = $this->executions->list($filters);

            return EjecucionPagoResource::collection($page->items())->additional([
                'success' => true,
                'pending_count' => $this->executions->pendingCount(),
                'totals' => $this->executions->totals($filters),
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ])->response();
        });
    }

    public function store(Request $request, int $providerId): JsonResponse
    {
        return $this->responder(function () use ($request, $providerId) {
            $actor = $this->authorization->requireAdministration($request->user());
            $data = $request->validate([
                'real_bank_fee' => ['required', 'numeric', 'min:0'],
                'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ]);

            return $this->ok(new EjecucionPagoResource($this->executions->register(
                $providerId,
                (float) $data['real_bank_fee'],
                $data['receipt'],
                $actor
            )), 201);
        });
    }

    private function responder(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (PagoProveedorException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], $exception->status);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Pago a proveedor ejecución: '.$exception->getMessage(), ['exception' => $exception]);

            return response()->json(['success' => false, 'message' => 'No se pudo completar la operación.'], 500);
        }
    }
}
