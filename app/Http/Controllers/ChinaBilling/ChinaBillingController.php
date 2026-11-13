<?php

namespace App\Http\Controllers\ChinaBilling;

use App\Exceptions\ChinaBillingException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChinaBilling\ChinaBillingRowResource;
use App\Models\ChinaBilling\ChinaOperation;
use App\Models\ChinaBilling\ChinaPayment;
use App\Services\ChinaBilling\ChinaBillingAuthorization;
use App\Services\ChinaBilling\ChinaBillingCalculator;
use App\Services\ChinaBilling\ChinaBillingReader;
use App\Services\ChinaBilling\ChinaOperationService;
use App\Services\ChinaBilling\ChinaPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Operaciones y cobros de Almacén China (ContenedorAlmacen).
 */
class ChinaBillingController extends Controller
{
    use RespondsChinaBilling;

    public function __construct(
        private readonly ChinaBillingAuthorization $authorization,
        private readonly ChinaBillingReader $reader,
        private readonly ChinaOperationService $operations,
        private readonly ChinaPaymentService $payments,
    ) {
    }

    public function operations(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $result = $this->reader->warehouseOperations($actor['organization_ids'], $request->all());

            return $this->listResponse($result);
        });
    }

    public function summary(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->authorization->requireWarehouse($request->user());

            return response()->json(['success' => true] + $this->reader->warehouseSummary($actor['organization_ids'], $request->only(['from', 'to'])));
        });
    }

    public function storeOperation(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $operation = $this->operations->create($this->operationData($request), $request->file('packing_list'), $actor);

            return $this->ok(new ChinaBillingRowResource($this->reader->operationDetail($operation->id)), 201);
        });
    }

    public function updateOperation(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $operation = $this->operations->update($id, $this->operationData($request), $request->file('packing_list'), $actor);

            return $this->ok(new ChinaBillingRowResource($this->reader->operationDetail($operation->id)));
        });
    }

    public function destroyOperation(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $this->operations->delete($id, $this->authorization->requireWarehouse($request->user()));

            return response()->json(['success' => true, 'message' => 'La operación se eliminó.']);
        });
    }

    public function dismissDraft(Request $request, int $containerId): JsonResponse
    {
        return $this->responder(function () use ($request, $containerId) {
            $this->operations->dismissDraft($containerId, $this->authorization->requireWarehouse($request->user()));

            return response()->json(['success' => true, 'message' => 'La operación por aprobar se eliminó.']);
        });
    }

    public function transferDifference(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $data = $request->validate(['target_operation_id' => ['required', 'integer']]);
            $this->operations->transferDifference($id, (int) $data['target_operation_id'], $actor);

            return $this->ok(new ChinaBillingRowResource($this->reader->operationDetail($id)));
        });
    }

    public function undoTransferDifference(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $hasPayments = $this->operations->undoTransferDifference($id, $this->authorization->requireWarehouse($request->user()));

            return (new ChinaBillingRowResource($this->reader->operationDetail($id)))
                ->additional([
                    'success' => true,
                    'warning' => $hasPayments ? 'La operación destino ya tiene pagos: revisa su saldo.' : null,
                ])->response();
        });
    }

    public function applyCredit(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $data = $request->validate([
                'amount' => ['required', 'numeric', 'gt:0'],
                'from_operation_id' => ['nullable', 'integer'],
            ]);
            $this->operations->applyCredit($id, (float) $data['amount'], $data['from_operation_id'] ?? null, $actor);

            return $this->ok(new ChinaBillingRowResource($this->reader->operationDetail($id)));
        });
    }

    public function undoCredit(Request $request, int $applicationId): JsonResponse
    {
        return $this->responder(function () use ($request, $applicationId) {
            $this->operations->undoCredit($applicationId, $this->authorization->requireWarehouse($request->user()));

            return response()->json(['success' => true, 'message' => 'El saldo a favor se devolvió.']);
        });
    }

    public function payments(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->authorization->requireWarehouse($request->user());

            return $this->listResponse($this->reader->warehousePayments($actor['organization_ids'], $request->all()));
        });
    }

    public function showPayment(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $this->payments->find($id, $actor, false);

            return $this->ok(new ChinaBillingRowResource($this->reader->paymentDetail($id)));
        });
    }

    public function storePayment(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $payment = $this->payments->register($this->paymentData($request, true), $request->file('receipt'), $actor, ChinaPayment::BY_CHINA);

            return $this->ok(new ChinaBillingRowResource($this->reader->paymentDetail($payment->id)), 201);
        });
    }

    public function updatePayment(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $payment = $this->payments->update($id, $this->paymentData($request, false), $request->file('receipt'), $actor);

            return $this->ok(new ChinaBillingRowResource($this->reader->paymentDetail($payment->id)));
        });
    }

    public function confirmPayment(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $data = $request->validate([
                'amount_received' => ['required', 'numeric', 'gt:0'],
                'shortfall_operation_id' => ['nullable', 'integer'],
            ]);
            $this->payments->confirm($id, (float) $data['amount_received'], $data['shortfall_operation_id'] ?? null, $actor);

            return $this->ok(new ChinaBillingRowResource($this->reader->paymentDetail($id)));
        });
    }

    public function unconfirmPayment(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $this->payments->unconfirm($id, $this->authorization->requireWarehouse($request->user()));

            return $this->ok(new ChinaBillingRowResource($this->reader->paymentDetail($id)));
        });
    }

    public function cashPayment(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $data = $request->validate([
                'exchange_rate' => ['required', 'numeric', 'gt:0'],
                'voucher' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ]);
            $this->payments->cash($id, (float) $data['exchange_rate'], $request->file('voucher'), $actor);

            return $this->ok(new ChinaBillingRowResource($this->reader->paymentDetail($id)));
        });
    }

    public function uncashPayment(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $this->payments->uncash($id, $this->authorization->requireWarehouse($request->user()));

            return $this->ok(new ChinaBillingRowResource($this->reader->paymentDetail($id)));
        });
    }

    public function destroyPayment(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $this->payments->delete($id, $this->authorization->requireWarehouse($request->user()));

            return response()->json(['success' => true, 'message' => 'El pago se eliminó.']);
        });
    }

    /** Operaciones pendientes del cliente, para repartir un pago. */
    public function pendingOperations(Request $request, int $organizationId): JsonResponse
    {
        return $this->responder(function () use ($request, $organizationId) {
            $actor = $this->authorization->requireWarehouse($request->user());
            if (!in_array($organizationId, $actor['organization_ids'], true)) {
                throw new ChinaBillingException('No tienes acceso a ese cliente.', 403);
            }
            $names = $this->reader->organizationNames([$organizationId]);
            $rows = $this->reader->operations([$organizationId], null, null)
                ->map(fn (ChinaOperation $op) => $this->reader->presentOperation($op, ChinaBillingCalculator::operationMetrics($op), $names))
                ->filter(fn (array $row) => $row['pending'] > 0)
                ->sortBy('issued_on')->values();

            return ChinaBillingRowResource::collection($rows)->additional(['success' => true])->response();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function operationData(Request $request): array
    {
        return $request->validate([
            'organization_id' => ['required', 'integer'],
            'container_id' => ['nullable', 'integer'],
            'service_name' => ['required', 'string', 'max:150'],
            'billing_type' => ['required', 'in:fixed,cbm'],
            'variant_name' => ['nullable', 'string', 'max:150'],
            'issued_on' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'cbm' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'fob' => ['nullable', 'numeric', 'min:0'],
            'freight' => ['nullable', 'numeric', 'min:0'],
            'costs' => ['nullable', 'array'],
            'costs.*.name' => ['nullable', 'string', 'max:150'],
            'costs.*.amount' => ['nullable', 'numeric', 'min:0'],
            'packing_list' => ['nullable', 'file', 'mimes:xls,xlsx', 'max:10240'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentData(Request $request, bool $withOrganization): array
    {
        return $request->validate([
            'organization_id' => [$withOrganization ? 'required' : 'nullable', 'integer'],
            'paid_on' => ['required', 'date'],
            'amount_sent' => ['required', 'numeric', 'gt:0'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.operation_id' => ['required', 'integer'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
    }
}
