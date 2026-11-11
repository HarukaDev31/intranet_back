<?php

namespace App\Http\Controllers\ChinaBilling;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChinaBilling\ChinaBillingRowResource;
use App\Models\ChinaBilling\ChinaClient;
use App\Services\ChinaBilling\ChinaBillingAuthorization;
use App\Services\ChinaBilling\ChinaClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Clientes de Almacén China para cobrar servicios (socios e importadores) y los clientes que registró cada organización.
 */
class ChinaClientController extends Controller
{
    use RespondsChinaBilling;

    public function __construct(
        private readonly ChinaBillingAuthorization $authorization,
        private readonly ChinaClientService $clients,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->authorization->requireWarehouse($request->user());

            return $this->listResponse($this->clients->list($actor['organization_ids'], $request->all(), $actor['id']));
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireWarehouse($request->user());

            return $this->ok(new ChinaBillingRowResource($this->clients->detail($id, $actor['organization_ids'])));
        });
    }

    public function store(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $data = $request->validate($this->rules(true));

            return $this->ok(new ChinaBillingRowResource($this->clients->create($data, $actor['organization_ids'], $actor['id'], $this->companyId($request))), 201);
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $data = $request->validate($this->rules(false));

            return $this->ok(new ChinaBillingRowResource($this->clients->update($id, $data, $actor['organization_ids'])));
        });
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $this->clients->delete($id, $actor['organization_ids']);

            return response()->json(['success' => true, 'message' => 'El cliente se eliminó.']);
        });
    }

    /** Clientes que registró una organización (solo lectura). */
    public function database(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->authorization->requireWarehouse($request->user());

            return $this->listResponse($this->clients->database($actor['organization_ids'], $request->all()));
        });
    }

    /** Países para crear la organización de un socio nuevo. */
    public function countries(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->authorization->requireWarehouse($request->user());

            return response()->json(['success' => true, 'data' => $this->clients->countryOptions()]);
        });
    }

    private function companyId(Request $request): ?int
    {
        $companyId = (int) $request->user()?->getAttribute('ID_Empresa');

        return $companyId > 0 ? $companyId : null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(bool $creating): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'country' => ['nullable', 'string', 'max:80'],
            'country_id' => ['nullable', 'integer'],
            'whatsapp' => ['required', 'string', 'max:40'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'prices' => ['nullable', 'array'],
            'prices.consol' => ['nullable', 'numeric', 'min:0'],
            'prices.consol_2' => ['nullable', 'numeric', 'min:0'],
        ];
        if ($creating) {
            $rules['type'] = ['required', 'in:'.ChinaClient::TYPE_PARTNER.','.ChinaClient::TYPE_IMPORTER];
            $rules['organization_id'] = ['nullable', 'integer'];
        }

        return $rules;
    }
}
