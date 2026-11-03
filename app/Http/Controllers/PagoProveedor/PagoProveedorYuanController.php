<?php

namespace App\Http\Controllers\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PagoProveedor\YuanPagoResource;
use App\Services\PagoProveedor\PagoProveedorAutorizacion;
use App\Services\PagoProveedor\PagoProveedorYuanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

            return YuanPagoResource::collection($page->items())->additional([
                'success' => true,
                'pending_count' => $this->yuan->pendingCount(),
                'indicators' => $this->yuan->indicators($request->all()),
                'counts' => $this->yuan->counts($request->all()),
                'partners' => $this->yuan->partners(),
                'reasons' => $this->yuan->reasons(),
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
                'real_exchange_rate' => ['required', 'numeric', 'gt:0'],
                'yuan_paid' => ['required', 'numeric', 'gt:0'],
                'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ]);
            $execution = $this->yuan->register(
                $providerId,
                (float) $data['real_exchange_rate'],
                (float) $data['yuan_paid'],
                $data['receipt'],
                $actor
            );

            return $this->ok(new YuanPagoResource($execution->proveedor), 201);
        });
    }

    public function storeManual(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $data = $request->validate([
                'partner' => ['required', 'string', 'max:80'],
                'client_name' => ['required', 'string', 'max:160'],
                'consolidation' => ['required', 'string', 'max:40'],
                'product' => ['required', 'string', 'max:160'],
                'supplier_code' => ['nullable', 'string', 'max:50'],
                'supplier_name' => ['nullable', 'string', 'max:160'],
                'seller_name' => ['required', 'string', 'max:120'],
                'wechat' => ['required', 'string', 'max:40'],
                'usd_amount' => ['required', 'numeric', 'gt:0'],
                'yuan_amount' => ['required', 'numeric', 'gt:0'],
                'proforma' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
            ]);
            $provider = $this->yuan->createManual($data, $request->file('proforma'), $actor);

            return $this->ok(new YuanPagoResource($provider), 201);
        });
    }

    public function updateManual(Request $request, int $providerId): JsonResponse
    {
        return $this->responder(function () use ($request, $providerId) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $data = $request->validate([
                'partner' => ['required', 'string', 'max:80'],
                'client_name' => ['required', 'string', 'max:160'],
                'consolidation' => ['required', 'string', 'max:40'],
                'product' => ['required', 'string', 'max:160'],
                'supplier_code' => ['nullable', 'string', 'max:50'],
                'supplier_name' => ['nullable', 'string', 'max:160'],
                'seller_name' => ['required', 'string', 'max:120'],
                'wechat' => ['required', 'string', 'max:40'],
                'usd_amount' => ['required', 'numeric', 'gt:0'],
                'yuan_amount' => ['required', 'numeric', 'gt:0'],
                'proforma' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
            ]);
            $provider = $this->yuan->updateManual($providerId, $data, $request->file('proforma'), $actor);

            return $this->ok(new YuanPagoResource($provider));
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
            $provider = $this->yuan->liftManual($providerId, $actor);

            return $this->ok(new YuanPagoResource($provider));
        });
    }

    public function destroy(Request $request, int $providerId): JsonResponse
    {
        return $this->responder(function () use ($request, $providerId) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $this->yuan->deleteManual($providerId, $actor);

            return response()->json(['success' => true, 'message' => 'La solicitud se eliminó.']);
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
