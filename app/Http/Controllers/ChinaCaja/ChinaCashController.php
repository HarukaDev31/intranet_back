<?php

namespace App\Http\Controllers\ChinaCaja;

use App\Http\Controllers\ChinaBilling\RespondsChinaBilling;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChinaCaja\ChinaCajaResource;
use App\Services\ChinaBilling\ChinaBillingAuthorization;
use App\Services\ChinaCaja\ChinaCashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Flujo de caja (solo Encargado China). */
class ChinaCashController extends Controller
{
    use RespondsChinaBilling;

    public function __construct(
        private readonly ChinaBillingAuthorization $authorization,
        private readonly ChinaCashService $cash,
    ) {
    }

    public function show(Request $request, string $month): JsonResponse
    {
        return $this->run($request, fn (array $actor) => $this->cash->month($actor['organization_ids'], $month));
    }

    public function history(Request $request, int $year): JsonResponse
    {
        return $this->run($request, fn (array $actor) => $this->cash->history($actor['organization_ids'], $year));
    }

    public function settings(Request $request, string $month): JsonResponse
    {
        return $this->run($request, function () use ($request, $month) {
            $data = $request->validate(['exchange_rate' => ['sometimes', 'numeric', 'gt:0', 'max:100'], 'opening_balance' => ['sometimes', 'numeric']]);
            if (isset($data['exchange_rate'])) {
                $this->cash->setRate($month, (float) $data['exchange_rate']);
            }
            if (isset($data['opening_balance'])) {
                $this->cash->setOpening($month, (float) $data['opening_balance']);
            }
        });
    }

    public function closeWeek(Request $request, string $month): JsonResponse
    {
        return $this->run($request, function (array $actor) use ($request, $month) {
            $data = $request->validate([
                'starts_on' => ['required', 'date_format:Y-m-d'], 'balance' => ['required', 'numeric'],
                'screenshot' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
            ]);
            $this->cash->closeWeek($month, $data['starts_on'], (float) $data['balance'], $request->file('screenshot'), $actor);
        });
    }

    public function closeMonth(Request $request, string $month): JsonResponse
    {
        return $this->run($request, function (array $actor) use ($request, $month) {
            $this->cash->closeMonth($month, (float) $request->validate(['balance' => ['required', 'numeric']])['balance'], $actor);
        });
    }

    public function storeFixed(Request $request): JsonResponse
    {
        return $this->run($request, fn () => $this->cash->createFixedCost($request->validate([
            'name' => ['required', 'string', 'max:150'], 'amount' => ['required', 'numeric', 'gt:0'],
        ])));
    }

    public function updateFixed(Request $request, int $id): JsonResponse
    {
        return $this->run($request, fn () => $this->cash->toggleFixedCost($id, (bool) $request->validate(['active' => ['required', 'boolean']])['active']));
    }

    public function destroyFixed(Request $request, int $id): JsonResponse
    {
        return $this->run($request, fn () => $this->cash->deleteFixedCost($id));
    }

    public function storeInvestment(Request $request): JsonResponse
    {
        return $this->run($request, fn () => $this->cash->createInvestment($request->validate([
            'name' => ['required', 'string', 'max:150'], 'amount' => ['required', 'numeric', 'gt:0'], 'due_on' => ['required', 'date_format:Y-m-d'],
            'previous_id' => ['nullable', 'integer', 'exists:china_investments,id'],
        ])));
    }

    public function payInvestment(Request $request, int $id): JsonResponse
    {
        return $this->run($request, fn () => $this->cash->payInvestment($id));
    }

    public function destroyInvestment(Request $request, int $id): JsonResponse
    {
        return $this->run($request, fn () => $this->cash->deleteInvestment($id));
    }

    private function run(Request $request, callable $action): JsonResponse
    {
        return $this->responder(function () use ($request, $action) {
            $actor = $this->authorization->requireWarehouse($request->user());

            $data = $action($actor);
            if ($data === null) {
                return response()->json(['success' => true, 'message' => 'Guardado.']);
            }

            return is_array($data) && array_is_list($data)
                ? ChinaCajaResource::collection($data)->additional(['success' => true])->response()
                : $this->ok(new ChinaCajaResource($data));
        });
    }
}
