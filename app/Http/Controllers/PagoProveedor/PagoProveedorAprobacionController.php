<?php

namespace App\Http\Controllers\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PagoProveedor\AprobacionPagoResource;
use App\Services\PagoProveedor\PagoProveedorAprobacionService;
use App\Services\PagoProveedor\PagoProveedorConfiguracionService;
use App\Services\PagoProveedor\PagoProveedorAutorizacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PagoProveedorAprobacionController extends Controller
{
    use RespondeConResource;

    public function __construct(
        private readonly PagoProveedorAutorizacion $authorization,
        private readonly PagoProveedorAprobacionService $approvals,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->authorization->requireAdministration($request->user());
            $page = $this->approvals->list($request->all(), $actor);

            return AprobacionPagoResource::collection($page->items())->additional([
                'success' => true,
                'pending_count' => $this->approvals->pendingCount(),
                'settings' => $this->approvals->settings(),
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

            return $this->ok(new AprobacionPagoResource($this->approvals->show($id)));
        });
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireAdministration($request->user());
            $data = $request->validate([
                'reason' => ['nullable', 'string', 'max:255'],
            ]);

            return $this->ok(new AprobacionPagoResource($this->approvals->approve($id, $data['reason'] ?? null, $actor)));
        });
    }

    public function collect(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireAdministration($request->user());

            return $this->ok(new AprobacionPagoResource($this->approvals->sendToCollect($id, $actor)));
        });
    }

    public function registrationError(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireAdministration($request->user());
            $data = $request->validate([
                'detail' => ['required', 'string', 'max:500'],
            ]);

            return $this->ok(new AprobacionPagoResource($this->approvals->markRegistrationError($id, $data['detail'], $actor)));
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

            return $this->ok(new AprobacionPagoResource($this->approvals->extendCollect(
                $id,
                $data['reason'],
                isset($data['days']) ? (int) $data['days'] : null,
                $actor
            )));
        });
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireAdministration($request->user());
            $data = $request->validate([
                'reason' => ['required', 'string', 'max:255'],
            ]);

            return $this->ok(new AprobacionPagoResource($this->approvals->cancel($id, $data['reason'], $actor)));
        });
    }

    public function updateSettings(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->authorization->requireAdministration($request->user());
            $data = $request->validate([
                'tolerance' => ['required', 'numeric', 'min:0', 'max:100'],
                'exception_limit' => ['required', 'numeric', 'min:0', 'max:100'],
                'remedy_days' => ['required', 'integer', 'min:1', 'max:60'],
                'verify_hours' => ['required', 'integer', 'min:1', 'max:72'],
                'undo_minutes' => ['required', 'integer', 'min:1', 'max:120'],
                'undo_hours' => ['required', 'integer', 'min:1', 'max:168'],
                'decision_hours' => ['required', 'integer', 'min:1', 'max:240'],
                'auto_approve' => ['required', 'boolean'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->approvals->saveSettings($data),
            ]);
        });
    }

    public function tariffs(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->authorization->requireAdministration($request->user());
            $tariff = app(PagoProveedorConfiguracionService::class)->tariff();

            return response()->json([
                'success' => true,
                'data' => [
                    'usd' => [$tariff->bancariaUsdBaja, $tariff->bancariaUsdMedia, $tariff->bancariaUsdAlta],
                    'rmb' => [$tariff->bancariaRmbBaja, $tariff->bancariaRmbMedia, $tariff->bancariaRmbAlta],
                    'tramo_medio' => $tariff->limiteMedio,
                    'tramo_alto' => $tariff->limiteAlto,
                    'admin' => $tariff->administrativa,
                    'admin_usd' => $tariff->administrativaUsd,
                    'admin_rmb' => $tariff->administrativaRmb,
                ],
            ]);
        });
    }

    public function updateTariffs(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->authorization->requireAdministration($request->user());
            $data = $request->validate([
                'usd' => ['nullable', 'array', 'size:3'],
                'usd.*' => ['numeric', 'min:0'],
                'rmb' => ['nullable', 'array', 'size:3'],
                'rmb.*' => ['numeric', 'min:0'],
                'tramo_medio' => ['nullable', 'numeric', 'min:0'],
                'tramo_alto' => ['nullable', 'numeric', 'min:0'],
                'admin' => ['nullable', 'numeric', 'min:0'],
                'admin_usd' => ['nullable', 'numeric', 'min:0'],
                'admin_rmb' => ['nullable', 'numeric', 'min:0'],
                'tipo' => ['nullable', 'in:bancaria,admin'],
            ]);
            $configuration = app(PagoProveedorConfiguracionService::class);
            $tipo = (string) ($data['tipo'] ?? 'bancaria');

            if ($tipo === 'admin') {
                $adminUsd = (float) ($data['admin_usd'] ?? $data['admin'] ?? 0);
                $adminRmb = (float) ($data['admin_rmb'] ?? $data['admin'] ?? $adminUsd);
                $configuration->save('comision_administrativa_usd', $adminUsd);
                $configuration->save('comision_administrativa_rmb', $adminRmb);
                $configuration->save('comision_administrativa', $adminUsd);
            } else {
                if (isset($data['usd'])) {
                    $configuration->save('tarifa_bancaria_usd', array_map('floatval', $data['usd']));
                }
                if (isset($data['rmb'])) {
                    $configuration->save('tarifa_bancaria_rmb', array_map('floatval', $data['rmb']));
                }
                if (isset($data['tramo_medio'])) {
                    $configuration->save('tramo_medio', (float) $data['tramo_medio']);
                }
                if (isset($data['tramo_alto'])) {
                    $configuration->save('tramo_alto', (float) $data['tramo_alto']);
                }
            }

            return response()->json(['success' => true, 'data' => $configuration->all()]);
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
            Log::error('Pago a proveedor aprobación: '.$exception->getMessage(), ['exception' => $exception]);

            return response()->json(['success' => false, 'message' => 'No se pudo completar la operación.'], 500);
        }
    }
}
