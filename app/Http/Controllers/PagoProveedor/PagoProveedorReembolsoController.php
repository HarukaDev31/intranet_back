<?php

namespace App\Http\Controllers\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PagoProveedor\ReembolsoResource;
use App\Models\PagoProveedor\PagoProveedorAbono;
use App\Services\PagoProveedor\PagoProveedorAutorizacion;
use App\Services\PagoProveedor\PagoProveedorReembolsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PagoProveedorReembolsoController extends Controller
{
    use RespondeConResource;

    public function __construct(
        private readonly PagoProveedorAutorizacion $authorization,
        private readonly PagoProveedorReembolsoService $refunds,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->authorization->requireAdministration($request->user());
            $page = $this->refunds->list($request->all());

            return ReembolsoResource::collection($page->items())->additional([
                'success' => true,
                'pending_count' => $this->refunds->pendingCount(),
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ])->response();
        });
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireAdministration($request->user());
            $data = $request->validate([
                'importe' => ['required', 'numeric', 'gt:0'],
                'fecha' => ['required', 'date'],
                'banco' => ['required', 'in:'.PagoProveedorAbono::BANK_BCP.','.PagoProveedorAbono::BANK_INTERBANK],
                'numero_operacion' => ['nullable', 'string', 'max:80'],
                'voucher' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ]);

            return $this->ok(new ReembolsoResource($this->refunds->approve(
                $id,
                (float) $data['importe'],
                $data['fecha'],
                $data['banco'],
                $data['numero_operacion'] ?? null,
                $data['voucher'],
                $actor
            )));
        });
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireAdministration($request->user());
            $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

            return $this->ok(new ReembolsoResource($this->refunds->reject($id, $data['reason'], $actor)));
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
            Log::error('Pago a proveedor reembolso: '.$exception->getMessage(), ['exception' => $exception]);

            return response()->json(['success' => false, 'message' => 'No se pudo completar la operación.'], 500);
        }
    }
}
