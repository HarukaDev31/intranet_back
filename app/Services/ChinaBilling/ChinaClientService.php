<?php

namespace App\Services\ChinaBilling;

use App\Exceptions\ChinaBillingException;
use App\Models\ChinaBilling\ChinaClient;
use App\Models\ChinaBilling\ChinaOperation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Clientes de Almacén China para cobrar servicios: socios (una fila por organización) e importadores.
 * Es independiente de la base de datos de clientes de Carga Consolidada.
 * Los totales salen de las operaciones del socio y se calculan aquí, nunca en el front.
 */
class ChinaClientService
{
    public function __construct(
        private readonly ChinaBillingReader $reader,
        private readonly ChinaDraftService $drafts,
    ) {
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @param  array<string, mixed>  $filters  type (all|partner|importer), search, page, per_page
     * @return array<string, mixed>
     */
    public function list(array $organizationIds, array $filters, ?int $userId): array
    {
        $this->syncPartners($organizationIds, $userId);
        $clients = $this->clients($organizationIds);
        $metrics = $this->metricsByOrganization($organizationIds);

        $rows = $clients->map(fn (ChinaClient $client) => $this->present($client, $metrics[$client->organization_id] ?? null))
            ->sortByDesc('registered_on')->values()
            ->map(fn (array $row, int $index) => ['number' => $index + 1] + $row);

        $type = (string) ($filters['type'] ?? 'all');
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $filtered = $rows->filter(function (array $row) use ($type, $search) {
            if ($type !== '' && $type !== 'all' && $row['type'] !== $type) {
                return false;
            }

            return $search === '' || str_contains(mb_strtolower(implode(' ', [
                $row['name'], $row['whatsapp'], $row['email'], $row['contact_name'], $row['country'],
            ])), $search);
        })->values();

        return [
            'page' => $this->paginate($filtered, $filters),
            'counts' => [
                'all' => $rows->count(),
                ChinaClient::TYPE_PARTNER => $rows->where('type', ChinaClient::TYPE_PARTNER)->count(),
                ChinaClient::TYPE_IMPORTER => $rows->where('type', ChinaClient::TYPE_IMPORTER)->count(),
            ],
            'organizations' => $this->reader->organizationOptions($organizationIds),
        ];
    }

    /**
     * Cliente con sus servicios (operaciones y operaciones por aprobar) para el detalle y el balance.
     *
     * @param  array<int, int>  $organizationIds
     * @return array<string, mixed>
     */
    public function detail(int $id, array $organizationIds): array
    {
        $client = $this->find($id, $organizationIds);
        $metrics = $client->organization_id ? $this->metricsByOrganization($organizationIds)[$client->organization_id] ?? null : null;
        $names = $client->organization_id ? $this->reader->organizationNames([(int) $client->organization_id]) : [];

        $services = [];
        if ($client->organization_id) {
            foreach ($this->reader->operations([(int) $client->organization_id], null, null) as $operation) {
                $services[] = $this->serviceRow($operation, $names);
            }
            foreach ($this->drafts->list([(int) $client->organization_id]) as $draft) {
                $services[] = [
                    'code' => null, 'issued_on' => $draft['issued_on'], 'service_name' => 'Consolidado', 'description' => $draft['description'],
                    'total' => null, 'received' => null, 'balance' => null, 'status' => 'draft',
                ];
            }
        }

        return $this->present($client, $metrics) + ['services' => $services];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $organizationIds
     * @return array<string, mixed>
     */
    public function create(array $data, array $organizationIds, ?int $userId): array
    {
        $type = (string) $data['type'];
        $this->requireUniqueName((string) $data['name'], null, $type === ChinaClient::TYPE_PARTNER ? (int) ($data['organization_id'] ?? 0) : null);

        if ($type === ChinaClient::TYPE_PARTNER) {
            $organizationId = (int) ($data['organization_id'] ?? 0);
            if (!in_array($organizationId, $organizationIds, true)) {
                throw new ChinaBillingException('Elige la organización del socio.');
            }
            $this->syncPartners([$organizationId], $userId);
            $client = ChinaClient::query()->where('organization_id', $organizationId)->firstOrFail();
            $this->fillContact($client, $data);
        } else {
            $client = new ChinaClient();
            $client->fill([
                'type' => ChinaClient::TYPE_IMPORTER, 'name' => trim((string) $data['name']), 'country' => trim((string) ($data['country'] ?? '')),
                'created_by' => $userId,
            ]);
            $this->fillContact($client, $data);
        }

        return $this->detail((int) $client->id, $organizationIds);
    }

    /**
     * Los socios toman nombre y país de su organización: aquí solo se editan contacto y tarifas.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $organizationIds
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, array $organizationIds): array
    {
        $client = $this->find($id, $organizationIds);
        if ($client->type === ChinaClient::TYPE_IMPORTER) {
            $this->requireUniqueName((string) $data['name'], (int) $client->id, null);
            $client->fill(['name' => trim((string) $data['name']), 'country' => trim((string) ($data['country'] ?? ''))]);
        }
        $this->fillContact($client, $data + ['type' => $client->type]);

        return $this->detail((int) $client->id, $organizationIds);
    }

    /**
     * @param  array<int, int>  $organizationIds
     */
    public function delete(int $id, array $organizationIds): void
    {
        $client = $this->find($id, $organizationIds);
        if ($client->type === ChinaClient::TYPE_PARTNER) {
            throw new ChinaBillingException('Los socios se administran desde su organización.');
        }
        $client->delete();
    }

    /**
     * Cada organización socio tiene su fila de cliente (se crea sola la primera vez).
     *
     * @param  array<int, int>  $organizationIds
     */
    public function syncPartners(array $organizationIds, ?int $userId): void
    {
        if (!$organizationIds) {
            return;
        }
        $names = $this->reader->organizationNames($organizationIds);
        $existing = ChinaClient::query()->whereIn('organization_id', $organizationIds)->get()->keyBy('organization_id');

        foreach ($names as $organizationId => $org) {
            $client = $existing->get($organizationId) ?? new ChinaClient(['organization_id' => $organizationId, 'type' => ChinaClient::TYPE_PARTNER, 'created_by' => $userId]);
            $client->fill(['name' => $org['name'], 'country' => $org['country'] !== '' ? $org['country'] : null]);
            if ($client->isDirty() || !$client->exists) {
                $client->save();
            }
        }
    }

    /**
     * Tarifas del socio por organización (para precargar la tarifa al crear una operación).
     *
     * @param  array<int, int>  $organizationIds
     * @return array<int, array{consol: float|null, consol_2: float|null}>
     */
    public function pricesByOrganization(array $organizationIds): array
    {
        return ChinaClient::query()->whereIn('organization_id', $organizationIds)->get()
            ->mapWithKeys(fn (ChinaClient $client) => [(int) $client->organization_id => $this->prices($client)])->all();
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @return Collection<int, ChinaClient>
     */
    private function clients(array $organizationIds): Collection
    {
        return ChinaClient::query()
            ->where(fn ($q) => $q->whereNull('organization_id')->orWhereIn('organization_id', $organizationIds ?: [0]))
            ->get();
    }

    /**
     * @param  array<int, int>  $organizationIds
     */
    private function find(int $id, array $organizationIds): ChinaClient
    {
        $client = ChinaClient::query()
            ->where(fn ($q) => $q->whereNull('organization_id')->orWhereIn('organization_id', $organizationIds ?: [0]))
            ->find($id);
        if (!$client) {
            throw new ChinaBillingException('El cliente no existe o no tienes acceso.', 404);
        }

        return $client;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fillContact(ChinaClient $client, array $data): void
    {
        $partner = ($data['type'] ?? $client->type) === ChinaClient::TYPE_PARTNER;
        $prices = null;
        if ($partner) {
            $prices = [];
            foreach (ChinaClient::PRICE_FIELDS as $field) {
                $value = (float) ($data['prices'][$field] ?? 0);
                if ($value <= 0) {
                    throw new ChinaBillingException('Ingresa las dos tarifas de consolidado del socio.');
                }
                $prices[$field] = $value;
            }
        }
        $client->fill([
            'whatsapp' => trim((string) ($data['whatsapp'] ?? '')),
            'contact_name' => trim((string) ($data['contact_name'] ?? '')) ?: null,
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'prices' => $prices,
        ]);
        if ($client->whatsapp === '') {
            throw new ChinaBillingException('Ingresa el WhatsApp del cliente.');
        }
        $client->save();
    }

    private function requireUniqueName(string $name, ?int $ignoreId, ?int $organizationId): void
    {
        $name = mb_strtolower(trim($name));
        if ($name === '') {
            throw new ChinaBillingException('Ingresa el nombre del cliente.');
        }
        $duplicate = ChinaClient::query()
            ->whereRaw('LOWER(name) = ?', [$name])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->when($organizationId, fn ($q) => $q->where('organization_id', '!=', $organizationId))
            ->exists();
        if ($duplicate) {
            throw new ChinaBillingException('Ya existe un cliente con ese nombre.');
        }
    }

    /**
     * @return array{consol: float|null, consol_2: float|null}
     */
    private function prices(ChinaClient $client): array
    {
        $prices = (array) ($client->prices ?? []);

        return [
            'consol' => isset($prices['consol']) ? (float) $prices['consol'] : null,
            'consol_2' => isset($prices['consol_2']) ? (float) $prices['consol_2'] : null,
        ];
    }

    /**
     * Totales por organización: servicio, facturado, pagado, por cobrar y operaciones abiertas/cerradas.
     *
     * @param  array<int, int>  $organizationIds
     * @return array<int, array<string, float|int>>
     */
    private function metricsByOrganization(array $organizationIds): array
    {
        $operations = $this->reader->operations($organizationIds, null, null)->groupBy('organization_id');
        $drafts = $this->drafts->list($organizationIds)->groupBy('organization_id');
        $result = [];

        foreach ($organizationIds as $organizationId) {
            $rows = ($operations->get($organizationId) ?? collect())->map(fn (ChinaOperation $op) => ChinaBillingCalculator::operationMetrics($op));
            $closed = $rows->where('status', ChinaBillingCalculator::STATUS_IN_CASH)->count();
            $result[(int) $organizationId] = [
                'operations_count' => $rows->count(),
                'service_amount' => ChinaBillingCalculator::round((float) $rows->sum('service_amount')),
                'total_billed' => ChinaBillingCalculator::round((float) $rows->sum('total')),
                'paid' => ChinaBillingCalculator::round((float) $rows->sum('received')),
                'receivable' => ChinaBillingCalculator::round((float) $rows->reject(fn ($m) => $m['transferred'])->sum(fn ($m) => max(0, $m['total'] - $m['received']))),
                'open_count' => $rows->count() - $closed + ($drafts->get($organizationId) ?? collect())->count(),
                'closed_count' => $closed,
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, float|int>|null  $metrics
     * @return array<string, mixed>
     */
    private function present(ChinaClient $client, ?array $metrics): array
    {
        $isPartner = $client->type === ChinaClient::TYPE_PARTNER;
        $metrics ??= ['operations_count' => 0, 'service_amount' => 0.0, 'total_billed' => 0.0, 'paid' => 0.0, 'receivable' => 0.0, 'open_count' => 0, 'closed_count' => 0];

        return [
            'id' => (int) $client->id,
            'type' => $client->type,
            'organization_id' => $client->organization_id ? (int) $client->organization_id : null,
            'name' => (string) $client->name,
            'country' => (string) ($client->country ?? ''),
            'registered_on' => $client->created_at?->toDateString(),
            'whatsapp' => (string) ($client->whatsapp ?? ''),
            'contact_name' => (string) ($client->contact_name ?? ''),
            'email' => (string) ($client->email ?? ''),
            'prices' => $isPartner ? $this->prices($client) : null,
            'operations_count' => (int) $metrics['operations_count'],
            'service_amount' => $metrics['service_amount'],
            'total_billed' => $metrics['total_billed'],
            'paid' => $metrics['paid'],
            'receivable' => $metrics['receivable'],
            'open_count' => (int) $metrics['open_count'],
            'closed_count' => (int) $metrics['closed_count'],
            'can_delete' => !$isPartner,
            'can_download_balance' => $isPartner && $client->organization_id !== null,
        ];
    }

    /**
     * @param  array<int, array{name: string, country: string}>  $names
     * @return array<string, mixed>
     */
    private function serviceRow(ChinaOperation $operation, array $names): array
    {
        $metrics = ChinaBillingCalculator::operationMetrics($operation);

        return [
            'code' => $operation->code,
            'issued_on' => $operation->issued_on?->toDateString(),
            'service_name' => $operation->service_name,
            'description' => $operation->description,
            'total' => $metrics['total'],
            'received' => $metrics['received'],
            'balance' => $metrics['transferred'] ? 0.0 : ChinaBillingCalculator::round(max(0, $metrics['total'] - $metrics['received'])),
            'status' => $metrics['status'],
        ];
    }

    /**
     * @template TRow of array<string, mixed>
     *
     * @param  Collection<int, TRow>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function paginate(Collection $rows, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 15)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page);
    }
}
