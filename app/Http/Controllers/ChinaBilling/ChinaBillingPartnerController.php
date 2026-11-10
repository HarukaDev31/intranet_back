<?php

namespace App\Http\Controllers\ChinaBilling;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChinaBilling\ChinaBillingRowResource;
use App\Models\ChinaBilling\ChinaOperation;
use App\Models\ChinaBilling\ChinaPayment;
use App\Services\ChinaBilling\ChinaBillingAuthorization;
use App\Services\ChinaBilling\ChinaBillingCalculator;
use App\Services\ChinaBilling\ChinaBillingReader;
use App\Services\ChinaBilling\ChinaPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pagos del Socio: solo ve y paga lo de su organización.
 */
class ChinaBillingPartnerController extends Controller
{
    use RespondsChinaBilling;

    public function __construct(
        private readonly ChinaBillingAuthorization $partnerAuthorization,
        private readonly ChinaBillingReader $partnerReader,
        private readonly ChinaPaymentService $partnerPayments,
    ) {
    }

    public function invoices(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->partnerAuthorization->requirePartner($request->user());

            return $this->listResponse($this->partnerReader->partnerInvoices($actor['organization_ids'], $request->all()));
        });
    }

    public function myPayments(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->partnerAuthorization->requirePartner($request->user());

            return $this->listResponse($this->partnerReader->partnerPayments($actor['organization_ids'], $request->all()));
        });
    }

    public function store(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->partnerAuthorization->requirePartner($request->user());
            $data = $this->data($request);
            $data['organization_id'] = $actor['organization_ids'][0];
            $payment = $this->partnerPayments->register($data, $request->file('receipt'), $actor, ChinaPayment::BY_PARTNER);

            return $this->ok(new ChinaBillingRowResource($this->partnerReader->paymentDetail($payment->id)), 201);
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->partnerAuthorization->requirePartner($request->user());
            $payment = $this->partnerPayments->update($id, $this->data($request), $request->file('receipt'), $actor);

            return $this->ok(new ChinaBillingRowResource($this->partnerReader->paymentDetail($payment->id)));
        });
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $this->partnerPayments->delete($id, $this->partnerAuthorization->requirePartner($request->user()), true);

            return response()->json(['success' => true, 'message' => 'El pago se anuló.']);
        });
    }

    /** Facturas con saldo por pagar, para repartir un pago. */
    public function payableInvoices(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->partnerAuthorization->requirePartner($request->user());
            $names = $this->partnerReader->organizationNames($actor['organization_ids']);
            $rows = $this->partnerReader->operations($actor['organization_ids'], null, null)
                ->map(fn (ChinaOperation $op) => $this->partnerReader->presentOperation($op, ChinaBillingCalculator::operationMetrics($op), $names))
                ->filter(fn (array $row) => $row['pending'] > 0)
                ->sortBy('issued_on')->values();

            return ChinaBillingRowResource::collection($rows)->additional(['success' => true])->response();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Request $request): array
    {
        return $request->validate([
            'paid_on' => ['required', 'date'],
            'amount_sent' => ['required', 'numeric', 'gt:0'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.operation_id' => ['required', 'integer'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
    }
}
