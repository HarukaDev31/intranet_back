<?php

namespace App\Http\Controllers\ChinaBilling;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChinaBilling\ChinaBillingRowResource;
use App\Services\ChinaBilling\ChinaBillingAuthorization;
use App\Services\ChinaBilling\ChinaClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Clientes de Almacén China para cobrar servicios (organizaciones socio o importador) y los clientes que registró cada organización.
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

            return $this->listResponse($this->clients->list($actor['organization_ids'], $request->all()));
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
            $data = $request->validate($this->rules());

            return $this->ok(new ChinaBillingRowResource($this->clients->create($data, $actor['organization_ids'], $actor['id'])), 201);
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->responder(function () use ($request, $id) {
            $actor = $this->authorization->requireWarehouse($request->user());
            $data = $request->validate($this->rules());

            return $this->ok(new ChinaBillingRowResource($this->clients->update($id, $data, $actor['organization_ids'], $actor['id'])));
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

    /** Países para el formulario del cliente (lista completa). */
    public function countries(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->authorization->requireWarehouse($request->user());

            return response()->json(['success' => true, 'data' => $this->clients->countryOptions()]);
        });
    }

    /**
     * Países paginados para el autocompletado de «Nueva operación» (search, page, per_page=5);
     * primero los países donde hay clientes.
     */
    public function countrySearch(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->authorization->requireWarehouse($request->user());

            return $this->listResponse($this->clients->countriesPage($actor['organization_ids'], $request->only(['search', 'page', 'per_page'])));
        });
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'type' => ['required', 'in:partner,importer'],
            'name' => ['required', 'string', 'max:100'],
            'origin_country_id' => ['required', 'integer', 'exists:pais,ID_Pais'],
            'consolidation_country_ids' => ['nullable', 'array'],
            'consolidation_country_ids.*' => ['integer', 'exists:pais,ID_Pais'],
            'whatsapp' => ['required', 'string', 'max:40'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:100'],
            'rate_1' => ['nullable', 'numeric', 'min:0'],
            'rate_2' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
