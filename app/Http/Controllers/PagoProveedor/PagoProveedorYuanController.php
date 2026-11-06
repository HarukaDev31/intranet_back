<?php

namespace App\Http\Controllers\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PagoProveedor\YuanPagoResource;
use App\Http\Resources\PagoProveedor\YuanSolicitudResource;
use App\Models\PagoProveedor\PagoProveedorSolicitud;
use App\Services\PagoProveedor\PagoProveedorAutorizacion;
use App\Services\PagoProveedor\PagoProveedorYuanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PagoProveedorYuanController extends Controller
{
    use RespondeConResource;

    public function __construct(
        private readonly PagoProveedorAutorizacion $authorization,
        private readonly PagoProveedorYuanService $yuan,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->authorization->requireWarehouse($request->user());
            $page = $this->yuan->list($request->all());

            return YuanSolicitudResource::collection($page->items())->additional([
                'success' => true,
                'pending_count' => $this->yuan->pendingCount(),
                'indicators' => $this->yuan->indicators(),
                'counts' => $this->yuan->counts(),
                'countries' => PagoProveedorSolicitud::COUNTRIES,
                'partners_by_country' => $this->yuan->partnersByCountry(),
                'clients_by_country' => $this->yuan->clientsByCountry(),
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
            $actor = $this->authorization->requireWarehouse($request->user());
            $data = $request->validate([
                'yuan_paid' => ['required', 'numeric', 'gt:0'],
                'paid_on' => ['nullable', 'date'],
                'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ]);
            $execution = $this->yuan->register(
                $providerId,
                (float) $data['yuan_paid'],
                $data['paid_on'] ?? null,
                $request->file('receipt'),
                $actor
            );

            return $this->ok(new YuanPagoResource($execution->proveedor), 201);
        });
    }

    public function undo(Request $request, int $providerId): JsonResponse
    {
        return $this->responder(function () use ($request, $providerId) {
            $actor = $this->authorization->requireWarehouse($request->user());

            return $this->ok(new YuanPagoResource($this->yuan->undoPayment($providerId, $actor)));
        });
    }

    public function storeIncome(Request $request, int $requestId): JsonResponse
    {
        return $this->responder(function () use ($request, $requestId) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $data = $request->validate([
                'usd_amount' => ['required', 'numeric', 'gt:0'],
                'exchange_rate' => ['required', 'numeric', 'gt:0'],
                'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ]);
            $this->yuan->addIncome($requestId, (float) $data['usd_amount'], (float) $data['exchange_rate'], $request->file('receipt'), $actor);

            return response()->json(['success' => true, 'message' => 'El ingreso quedó registrado.'], 201);
        });
    }

    public function destroyIncome(Request $request, int $incomeId): JsonResponse
    {
        return $this->responder(function () use ($request, $incomeId) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $this->yuan->removeIncome($incomeId, $actor);

            return response()->json(['success' => true, 'message' => 'El ingreso se quitó.']);
        });
    }

    public function storeManual(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $request_ = $this->yuan->createManual($request->validate($this->manualRules()), $actor);

            return response()->json(['success' => true, 'data' => ['request_id' => $request_->id]], 201);
        });
    }

    public function updateManual(Request $request, int $requestId): JsonResponse
    {
        return $this->responder(function () use ($request, $requestId) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $request_ = $this->yuan->updateManual($requestId, $request->validate($this->manualRules()), $actor);

            return response()->json(['success' => true, 'data' => ['request_id' => $request_->id]]);
        });
    }

    public function notice(Request $request, int $providerId): JsonResponse
    {
        return $this->responder(function () use ($request, $providerId) {
            if ($request->isMethod('GET')) {
                $this->authorization->requireWarehouse($request->user());

                return response()->json([
                    'success' => true,
                    'data' => ['message' => $this->yuan->noticeText($providerId)],
                ]);
            }
            $actor = $this->authorization->requireWarehouse($request->user());
            $data = $request->validate([
                'yuan_paid' => ['required', 'numeric', 'gt:0'],
            ]);
            $provider = $this->yuan->confirmNotice($providerId, (float) $data['yuan_paid'], $actor);

            return $this->ok(new YuanPagoResource($provider));
        });
    }

    public function lift(Request $request, int $providerId): JsonResponse
    {
        return $this->responder(function () use ($request, $providerId) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $provider = $this->yuan->clearObservation($providerId, $actor);

            return $this->ok(new YuanPagoResource($provider));
        });
    }

    public function observe(Request $request, int $providerId): JsonResponse
    {
        return $this->responder(function () use ($request, $providerId) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $data = $request->validate([
                'reason' => ['nullable', 'string', 'max:80'],
                'detail' => ['nullable', 'string', 'max:500'],
                'new_reason' => ['nullable', 'string', 'max:80'],
            ]);
            $provider = $this->yuan->observe(
                $providerId,
                (string) ($data['reason'] ?? ''),
                $data['detail'] ?? null,
                $data['new_reason'] ?? null,
                $actor
            );

            return $this->ok(new YuanPagoResource($provider));
        });
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function manualRules(): array
    {
        return [
            'country' => ['required', 'string', Rule::in(PagoProveedorSolicitud::COUNTRIES)],
            'partner' => ['required', 'string', 'max:80'],
            'request_date' => ['required', 'date'],
            'exchange_rate' => ['required', 'numeric', 'gt:0'],
            'providers' => ['required', 'array', 'min:1'],
            'providers.*.id' => ['nullable', 'integer'],
            'providers.*.client_name' => ['required', 'string', 'max:255'],
            'providers.*.supplier_name' => ['required', 'string', 'max:255'],
            'providers.*.wechat' => ['required', 'string', 'max:30'],
            'providers.*.product' => ['nullable', 'string', 'max:255'],
            'providers.*.seller_name' => ['nullable', 'string', 'max:255'],
            'providers.*.yuan_amount' => ['required', 'numeric', 'gt:0'],
            'providers.*.proforma' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
        ];
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
            Log::error('Pago a proveedor yuanes: '.$exception->getMessage(), ['exception' => $exception]);

            return response()->json(['success' => false, 'message' => 'No se pudo completar la operación.'], 500);
        }
    }
}
