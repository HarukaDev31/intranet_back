<?php

namespace App\Http\Controllers\ChinaCaja;

use App\Http\Controllers\ChinaBilling\RespondsChinaBilling;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChinaCaja\ChinaCajaResource;
use App\Services\ChinaBilling\ChinaBillingAuthorization;
use App\Services\ChinaCaja\ChinaCashService;
use App\Services\ChinaCaja\ChinaContainerRules;
use App\Services\ChinaCaja\ChinaContainerService;
use App\Services\ChinaCaja\ChinaPendingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Etapas, Estado de contenedores y bandeja del Inicio (solo Encargado China). */
class ChinaContainerController extends Controller
{
    use RespondsChinaBilling;

    private const FILE_RULE = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'];

    public function __construct(
        private readonly ChinaBillingAuthorization $authorization,
        private readonly ChinaContainerService $containers,
        private readonly ChinaCashService $cash,
        private readonly ChinaPendingService $pending,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $rows = $request->query('scope') === 'stages'
                ? $this->containers->rows($actor['organization_ids'])
                : $this->containers->shipping($actor['organization_ids']);

            return ChinaCajaResource::collection($rows->values())->additional(['success' => true, 'options' => $this->containers->options()])->response();
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $data = $request->validate([
                'freight_usd' => ['nullable', 'numeric', 'min:0', 'max:999999'],
                'filling_on' => ['nullable', 'date_format:Y-m-d'], 'sailing_on' => ['nullable', 'date_format:Y-m-d'], 'arrival_on' => ['nullable', 'date_format:Y-m-d'],
                'shipping_line' => ['nullable', 'string', 'max:100'], 'destination_port' => ['nullable', 'string', 'max:100'],
                'free_days' => ['nullable', 'string', 'max:60'], 'shipper' => ['nullable', 'string', 'max:100'],
                'container_type' => ['nullable', Rule::in(ChinaContainerRules::CONTAINER_TYPES)],
                'bl_consignee' => ['nullable', 'string', 'max:200'], 'bl_description' => ['nullable', 'string', 'max:255'], 'bl_number' => ['nullable', 'string', 'max:60'],
                'list_updated' => ['sometimes', 'boolean'], 'telex' => ['sometimes', 'boolean'],
                'remove_bl_draft' => ['sometimes', 'boolean'], 'remove_bl_official' => ['sometimes', 'boolean'],
                'bl_draft' => self::FILE_RULE, 'bl_official' => self::FILE_RULE,
            ]);
            unset($data['bl_draft'], $data['bl_official']);
            $files = ['bl_draft' => $request->file('bl_draft'), 'bl_official' => $request->file('bl_official')];

            return $this->ok(new ChinaCajaResource($this->containers->update($id, $data, $files, $actor)));
        });
    }

    public function payFreight(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $data = $request->validate([
                'container_ids' => ['required', 'array', 'min:1'], 'container_ids.*' => ['integer'],
                'paid_on' => ['required', 'date_format:Y-m-d'], 'manual' => ['sometimes', 'boolean'], 'voucher' => self::FILE_RULE,
            ]);
            $this->cash->guardOpen(substr($data['paid_on'], 0, 7));
            $payment = $this->containers->payFreight($data['container_ids'], $data['paid_on'], $request->file('voucher'),
                (bool) ($data['manual'] ?? false), $this->cash->rateFor($data['paid_on']), $actor);

            return $this->ok(new ChinaCajaResource(['id' => $payment->id]), 201);
        });
    }

    public function cancelFreight(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $this->cash->guardOpen(substr((string) $this->containers->freightPayment($id)->paid_on, 0, 7));
            $this->containers->cancelFreight($id, $actor);

            return response()->json(['success' => true, 'message' => 'Pago anulado.']);
        });
    }

    public function pending(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->authorization->requireWarehouse($request->user());

            return ChinaCajaResource::collection($this->pending->list($actor['organization_ids']))->additional(['success' => true])->response();
        });
    }
}
