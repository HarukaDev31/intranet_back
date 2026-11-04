<?php

namespace App\Http\Controllers\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PagoProveedor\DepositoVerificacionResource;
use App\Models\PagoProveedor\PagoProveedorAbono;
use App\Services\PagoProveedor\PagoProveedorAutorizacion;
use App\Services\PagoProveedor\PagoProveedorVerificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PagoProveedorVerificacionController extends Controller
{
    use RespondeConResource;

    public function __construct(
        private readonly PagoProveedorAutorizacion $authorization,
        private readonly PagoProveedorVerificacionService $deposits,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->authorization->requireAdministration($request->user());
            $page = $this->deposits->list($request->all());

            return DepositoVerificacionResource::collection($page->items())->additional([
                'success' => true,
                'pending_count' => $this->deposits->pendingCount(),
                'reasons' => $this->deposits->reasons(),
                'consolidations' => $this->deposits->consolidations(),
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ])->response();
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $this->authorization->requireAdministration($request->user());

            return $this->ok(new DepositoVerificacionResource($this->deposits->show($id)));
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireAdministration($request->user());
            $data = $request->validate([
                'bank' => ['required', 'in:'.PagoProveedorAbono::BANK_BCP.','.PagoProveedorAbono::BANK_INTERBANK],
                'paid_on' => ['required', 'date'],
                'operation_number' => ['required', 'string', 'max:80'],
            ]);

            return $this->ok(new DepositoVerificacionResource($this->deposits->updateDetails($id, $data, $actor)));
        });
    }

    public function validateDeposit(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireAdministration($request->user());
            $data = $request->validate([
                'bank_amount' => ['required', 'numeric', 'gt:0'],
            ]);

            return $this->ok(new DepositoVerificacionResource(
                $this->deposits->validateDeposit($id, (float) $data['bank_amount'], $actor)
            ));
        });
    }

    public function observe(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireAdministration($request->user());
            $data = $request->validate([
                'reason' => ['nullable', 'string', 'max:80'],
                'detail' => ['nullable', 'string', 'max:500'],
                'new_label' => ['nullable', 'string', 'max:120'],
            ]);

            return $this->ok(new DepositoVerificacionResource($this->deposits->observe(
                $id,
                (string) ($data['reason'] ?? ''),
                $data['detail'] ?? null,
                $data['new_label'] ?? null,
                $actor
            )));
        });
    }

    public function undo(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireAdministration($request->user());

            return $this->ok(new DepositoVerificacionResource($this->deposits->undo($id, $actor)));
        });
    }

    public function extend(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireAdministration($request->user());
            $data = $request->validate([
                'reason' => ['required', 'string', 'max:255'],
                'days' => ['nullable', 'integer', 'min:1', 'max:60'],
            ]);

            return $this->ok(new DepositoVerificacionResource($this->deposits->extendObservation(
                $id,
                $data['reason'],
                isset($data['days']) ? (int) $data['days'] : null,
                $actor
            )));
        });
    }

    public function cancelPayment(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireAdministration($request->user());
            $data = $request->validate([
                'reason' => ['required', 'string', 'max:255'],
            ]);

            return $this->ok(new DepositoVerificacionResource(
                $this->deposits->cancelObservedPayment($id, $data['reason'], $actor)
            ));
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
            Log::error('Pago a proveedor verificación: '.$exception->getMessage(), ['exception' => $exception]);

            return response()->json(['success' => false, 'message' => 'No se pudo completar la operación.'], 500);
        }
    }
}
