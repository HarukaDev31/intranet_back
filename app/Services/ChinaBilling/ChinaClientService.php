<?php

namespace App\Services\ChinaBilling;

use App\Exceptions\ChinaBillingException;
use App\Exceptions\OrganizacionClienteException;
use App\Models\BaseDatos\Clientes\Cliente;
use App\Models\ChinaBilling\ChinaOperation;
use App\Models\Organizacion;
use App\Models\Usuario;
use App\Services\BaseDatos\Clientes\ClienteService;
use App\Services\Organizacion\OrganizacionClienteService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Clientes de Almacén China para cobrar servicios: cada cliente es una organización (socio o importador).
 * El alta y la edición pasan por OrganizacionClienteService (el mismo de Panel de acceso › Organizaciones).
 * Los clientes que registró cada organización se muestran aparte, solo de lectura.
 * Los totales salen de las operaciones de la organización y se calculan aquí, nunca en el front.
 */
class ChinaClientService
{
    public const TYPE_PARTNER = 'partner';

    public const TYPE_IMPORTER = 'importer';

    public function __construct(
        private readonly ChinaBillingReader $reader,
        private readonly ChinaDraftService $drafts,
        private readonly OrganizacionClienteService $organizations,
    ) {
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @param  array<string, mixed>  $filters  type (all|partner|importer), search, page, per_page
     * @return array<string, mixed>
     */
    public function list(array $organizationIds, array $filters): array
    {
        $clients = $this->clients($organizationIds);
        $metrics = $this->metricsByOrganization($clients->map(fn (Organizacion $org) => (int) $org->getKey())->all());

        $rows = $clients->map(fn (Organizacion $org) => $this->present($org, $metrics[(int) $org->getKey()] ?? null))
            ->sortByDesc(fn (array $row) => [$row['registered_on'] ?? '', $row['id']])->values()
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
                self::TYPE_PARTNER => $rows->where('type', self::TYPE_PARTNER)->count(),
                self::TYPE_IMPORTER => $rows->where('type', self::TYPE_IMPORTER)->count(),
            ],
            'organizations' => $this->reader->organizationOptions($organizationIds),
            'groups' => $this->groups($organizationIds),
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
        $org = $this->find($id, $organizationIds);
        $organizationId = (int) $org->getKey();
        $metrics = $this->metricsByOrganization([$organizationId])[$organizationId] ?? null;
        $names = $this->reader->organizationNames([$organizationId]);

        $services = [];
        foreach ($this->reader->operations([$organizationId], null, null) as $operation) {
            $services[] = $this->serviceRow($operation, $names);
        }
        foreach ($this->drafts->list([$organizationId]) as $draft) {
            $services[] = [
                'code' => null, 'issued_on' => $draft['issued_on'], 'service_name' => 'Consolidado', 'description' => $draft['description'],
                'total' => null, 'received' => null, 'balance' => null, 'status' => 'draft',
            ];
        }

        return $this->present($org, $metrics) + ['services' => $services];
    }

    /**
     * Alta de un cliente: crea su organización (socio o importador) con rol, menú de Pagos y usuario si hay correo.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $organizationIds
     * @return array<string, mixed>
     */
    public function create(array $data, array $organizationIds, ?int $userId): array
    {
        $org = $this->save($data, null, $userId);
        $organizationIds[] = (int) $org->getKey();

        return $this->detail((int) $org->getKey(), $organizationIds);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $organizationIds
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, array $organizationIds, ?int $userId = null): array
    {
        $org = $this->find($id, $organizationIds);
        if ((int) $org->getKey() === Usuario::ID_ORGANIZACION_ADMIN) {
            // Pro Business (org 1) es la organización principal: desde Clientes solo se cambian sus tarifas.
            $this->updateHouseRates($org, $data, $userId);
        } else {
            $this->save($data, (int) $org->getKey(), $userId);
        }

        return $this->detail($id, $organizationIds);
    }

    /**
     * Solo se pueden quitar importadores sin operaciones: la organización queda inactiva (no se borra).
     *
     * @param  array<int, int>  $organizationIds
     */
    public function delete(int $id, array $organizationIds): void
    {
        $org = $this->find($id, $organizationIds);
        if ((int) $org->getKey() === Usuario::ID_ORGANIZACION_ADMIN) {
            throw new ChinaBillingException('Pro Business no se puede eliminar.');
        }
        if (OrganizacionClienteService::normalizarTipo($org->getAttribute('tipo_cliente')) !== Organizacion::TIPO_IMPORTADOR) {
            throw new ChinaBillingException('Los socios se administran desde su organización.');
        }
        if (ChinaOperation::query()->where('organization_id', $org->getKey())->exists()) {
            throw new ChinaBillingException('El importador tiene operaciones: no se puede eliminar.');
        }
        $org->setAttribute('Nu_Estado', 0);
        $org->save();
    }

    /**
     * Tarifas 1 y 2 de Pro Business (con historial), sin tocar su tipo, rol, menú ni usuarios.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateHouseRates(Organizacion $org, array $data, ?int $userId): void
    {
        try {
            $rate1 = OrganizacionClienteService::normalizarTarifa($data['rate_1'] ?? null, 'Tarifa 1');
            $rate2 = OrganizacionClienteService::normalizarTarifa($data['rate_2'] ?? null, 'Tarifa 2');
        } catch (OrganizacionClienteException $exception) {
            throw new ChinaBillingException($exception->getMessage(), $exception->status);
        }
        if ($rate1 === null || $rate2 === null) {
            throw new ChinaBillingException('Ingresa la tarifa 1 y la tarifa 2.');
        }
        $before1 = $org->getAttribute('tarifa_1');
        $before2 = $org->getAttribute('tarifa_2');
        if (!OrganizacionClienteService::tarifasCambiaron($before1, $before2, $rate1, $rate2)) {
            return;
        }
        DB::transaction(function () use ($org, $rate1, $rate2, $userId) {
            $org->setAttribute('tarifa_1', $rate1);
            $org->setAttribute('tarifa_2', $rate2);
            $org->save();
            DB::table('organizacion_tarifas_historial')->insert([
                'organizacion_id' => (int) $org->getKey(),
                'tarifa_1' => $rate1,
                'tarifa_2' => $rate2,
                'vigente_desde' => now(),
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Payload de Clientes China → campos de la organización.
     *
     * @param  array<string, mixed>  $data  type, name, origin_country_id, consolidation_country_ids, whatsapp, contact_name, email, rate_1, rate_2
     * @return array<string, mixed>
     */
    public static function organizationData(array $data): array
    {
        $type = OrganizacionClienteService::normalizarTipo($data['type'] ?? null);
        $isPartner = $type === Organizacion::TIPO_SOCIO;

        return [
            'tipo_cliente' => $type,
            'no_organizacion' => (string) ($data['name'] ?? ''),
            'pais_origen_id' => $data['origin_country_id'] ?? null,
            'paises_habilitados' => $isPartner ? (array) ($data['consolidation_country_ids'] ?? []) : [],
            'whatsapp' => $data['whatsapp'] ?? null,
            'contacto' => $data['contact_name'] ?? null,
            'correo' => $data['email'] ?? null,
            'tarifa_1' => $isPartner ? ($data['rate_1'] ?? null) : null,
            'tarifa_2' => $isPartner ? ($data['rate_2'] ?? null) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(array $data, ?int $organizationId, ?int $userId): Organizacion
    {
        $fields = self::organizationData($data);
        if (!(int) ($fields['pais_origen_id'] ?? 0)) {
            throw new ChinaBillingException('Elige el país de origen del cliente.');
        }
        if (trim((string) $fields['whatsapp']) === '') {
            throw new ChinaBillingException('Ingresa el WhatsApp del cliente.');
        }
        if ($fields['tipo_cliente'] === Organizacion::TIPO_SOCIO && (!((float) $fields['tarifa_1'] > 0) || !((float) $fields['tarifa_2'] > 0))) {
            throw new ChinaBillingException('Ingresa la tarifa 1 y la tarifa 2 del socio.');
        }

        try {
            return $this->organizations->guardar($fields, $organizationId, $userId);
        } catch (OrganizacionClienteException $exception) {
            throw new ChinaBillingException($exception->getMessage(), $exception->status);
        }
    }

    /**
     * Países disponibles para crear una organización.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public function countryOptions(): array
    {
        return DB::table('pais')->orderBy('No_Pais')->get(['ID_Pais', 'No_Pais'])
            ->map(fn ($row) => ['value' => (int) $row->ID_Pais, 'label' => (string) $row->No_Pais])->all();
    }

    /**
     * Países de la tabla global `pais` para el autocompletado de «Nueva operación», paginados.
     * Primero los países donde hay clientes (país de origen o país donde pueden consolidar).
     *
     * @param  array<int, int>  $organizationIds
     * @param  array<string, mixed>  $filters  search, page, per_page (5 por defecto)
     * @return array<string, mixed>
     */
    public function countriesPage(array $organizationIds, array $filters): array
    {
        $clientCountries = $this->clientCountryIds($organizationIds);
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = max(1, min(50, (int) ($filters['per_page'] ?? 5)));
        $ids = implode(',', array_map('intval', $clientCountries));

        $page = DB::table('pais')
            ->when($search !== '', fn ($q) => $q->where('No_Pais', 'like', '%'.$search.'%'))
            ->when($ids !== '', fn ($q) => $q->orderByRaw("CASE WHEN ID_Pais IN ({$ids}) THEN 0 ELSE 1 END"))
            ->orderBy('No_Pais')
            ->orderBy('ID_Pais')
            ->paginate($perPage, ['ID_Pais', 'No_Pais'], 'page', max(1, (int) ($filters['page'] ?? 1)));

        $rows = collect($page->items())->map(fn ($row) => [
            'value' => (int) $row->ID_Pais,
            'label' => (string) $row->No_Pais,
            'has_clients' => in_array((int) $row->ID_Pais, $clientCountries, true),
        ])->values()->all();

        return ['page' => new LengthAwarePaginator($rows, $page->total(), $page->perPage(), $page->currentPage())];
    }

    /**
     * Países de origen y países para consolidar de las organizaciones clientes activas.
     *
     * @param  array<int, int>  $organizationIds
     * @return array<int, int>
     */
    public function clientCountryIds(array $organizationIds): array
    {
        $ids = array_values(array_filter($organizationIds, fn (int $id) => $id !== Usuario::ID_ORGANIZACION_ADMIN)) ?: [0];
        $origin = DB::table('organizacion')->whereIn('ID_Organizacion', $ids)->where('Nu_Estado', 1)->whereNotNull('pais_origen_id')->pluck('pais_origen_id');
        $enabled = DB::table('organizacion_paises_habilitados as h')
            ->join('organizacion as o', 'o.ID_Organizacion', '=', 'h.organizacion_id')
            ->whereIn('h.organizacion_id', $ids)->where('o.Nu_Estado', 1)
            ->pluck('h.id_pais');

        return $origin->concat($enabled)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
    }

    /**
     * Clientes que registró una organización (solo lectura).
     *
     * @param  array<int, int>  $organizationIds
     * @param  array<string, mixed>  $filters  organization_id, search, page, per_page
     * @return array<string, mixed>
     */
    public function database(array $organizationIds, array $filters): array
    {
        $organizationId = (int) ($filters['organization_id'] ?? 0);
        $ids = in_array($organizationId, $this->withAdmin($organizationIds), true) ? [$organizationId] : [];
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 15)));

        $query = Cliente::query()->withoutGlobalScopes()->whereIn('organizacion_id', $ids ?: [0])
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(fn ($w) => $w->where('nombre', 'like', $like)->orWhere('documento', 'like', $like)
                    ->orWhere('telefono', 'like', $like)->orWhere('correo', 'like', $like)->orWhere('empresa', 'like', $like));
            })
            ->orderByDesc('fecha')->orderByDesc('id');

        $page = $query->paginate($perPage, ['*'], 'page', max(1, (int) ($filters['page'] ?? 1)));
        $service = app(ClienteService::class);
        $startNumber = ($page->currentPage() - 1) * $page->perPage();

        $rows = collect($page->items())->values()->map(function (Cliente $cliente, int $index) use ($service, $startNumber) {
            $first = $cliente->primer_servicio;
            $origin = $first ? $service->getProvinciaYOrigenParaCliente($cliente, [$first]) : ['provincia' => null, 'origen' => null];

            return [
                'id' => (int) $cliente->id,
                'number' => $startNumber + $index + 1,
                'registered_on' => $cliente->getAttribute('fecha') instanceof \DateTimeInterface ? $cliente->getAttribute('fecha')->format('Y-m-d') : null,
                'name' => (string) $cliente->getAttribute('nombre'),
                'document' => (string) ($cliente->documento ?? ''),
                'phone' => (string) ($cliente->telefono ?? ''),
                'email' => (string) ($cliente->correo ?? ''),
                'province' => (string) ($origin['provincia'] ?? ''),
                'origin' => (string) ($origin['origen'] ?? ''),
                'service' => $first ? trim(($first['servicio'] ?? '').' '.($first['detalle'] ?? '')) : '',
                'category' => $first ? (string) ($first['categoria'] ?? 'Cliente') : 'Prospecto',
            ];
        })->all();

        return [
            'page' => new LengthAwarePaginator($rows, $page->total(), $page->perPage(), $page->currentPage()),
            'organization_id' => $organizationId,
        ];
    }

    /**
     * Una pestaña por organización con la cantidad de clientes que registró.
     * Incluye la organización 1 (Perú, Pro Business) primero, aunque no sea socio.
     *
     * @param  array<int, int>  $organizationIds
     * @return array<int, array{organization_id: int, label: string, name: string, country: string, count: int}>
     */
    private function groups(array $organizationIds): array
    {
        $ids = $this->withAdmin($organizationIds);
        $names = $this->reader->organizationNames($ids);
        $counts = DB::table('clientes')->whereIn('organizacion_id', $ids)
            ->selectRaw('organizacion_id, COUNT(*) as total')->groupBy('organizacion_id')->pluck('total', 'organizacion_id');

        $result = [];
        foreach ($names as $organizationId => $org) {
            $isAdmin = (int) $organizationId === Usuario::ID_ORGANIZACION_ADMIN;
            $country = $isAdmin && $org['country'] === '' ? 'Perú' : $org['country'];
            $result[] = [
                'organization_id' => (int) $organizationId,
                // La pestaña se nombra por país, como en la maqueta; sin país se usa el nombre de la organización.
                'label' => $isAdmin ? 'Perú' : ($country !== '' ? mb_convert_case($country, MB_CASE_TITLE, 'UTF-8') : $org['name']),
                'name' => $org['name'],
                'country' => $country,
                'count' => (int) ($counts[$organizationId] ?? 0),
            ];
        }
        // Si dos organizaciones comparten país, la que no es la organización 1 se nombra por sí misma.
        // «Perú» y «PERU» cuentan como el mismo país.
        $key = fn (string $label): string => strtolower((string) preg_replace('/[^A-Za-z]/', '', (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label)));
        $labels = array_count_values(array_map($key, array_column($result, 'label')));
        foreach ($result as $index => $row) {
            if ($labels[$key($row['label'])] > 1 && $row['organization_id'] !== Usuario::ID_ORGANIZACION_ADMIN) {
                $result[$index]['label'] = $row['name'];
            }
        }
        usort($result, function (array $a, array $b) {
            $aAdmin = $a['organization_id'] === Usuario::ID_ORGANIZACION_ADMIN ? 0 : 1;
            $bAdmin = $b['organization_id'] === Usuario::ID_ORGANIZACION_ADMIN ? 0 : 1;

            return [$aAdmin, $a['label']] <=> [$bAdmin, $b['label']];
        });

        return $result;
    }

    /**
     * La base de datos de clientes de la organización 1 (Perú) también se consulta desde aquí.
     *
     * @param  array<int, int>  $organizationIds
     * @return array<int, int>
     */
    private function withAdmin(array $organizationIds): array
    {
        return array_values(array_unique([Usuario::ID_ORGANIZACION_ADMIN, ...$organizationIds]));
    }

    /**
     * Organizaciones clientes: las que ve Almacén China y Pro Business (org 1), que también paga sus consolidados.
     *
     * @param  array<int, int>  $organizationIds
     * @return Collection<int, Organizacion>
     */
    private function clients(array $organizationIds): Collection
    {
        return Organizacion::query()->with('paisOrigen')->whereIn('ID_Organizacion', $this->withAdmin($organizationIds))
            // Pro Business se lista aunque su estado en la tabla no sea 1 (es la organización principal).
            ->where(fn ($query) => $query->where('Nu_Estado', 1)->orWhere('ID_Organizacion', Usuario::ID_ORGANIZACION_ADMIN))->get()->toBase();
    }

    /**
     * @param  array<int, int>  $organizationIds
     */
    private function find(int $id, array $organizationIds): Organizacion
    {
        $org = ($id === Usuario::ID_ORGANIZACION_ADMIN || in_array($id, $organizationIds, true))
            ? Organizacion::query()->with('paisOrigen')->find($id)
            : null;
        if (!$org) {
            throw new ChinaBillingException('El cliente no existe o no tienes acceso.', 404);
        }

        return $org;
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
    private function present(Organizacion $org, ?array $metrics): array
    {
        $data = OrganizacionClienteService::serializar($org);
        $isHouse = (int) $org->getKey() === Usuario::ID_ORGANIZACION_ADMIN;
        // Pro Business se muestra como socio: tiene tarifas 1 y 2 para sus consolidados.
        $isPartner = $isHouse || $data['tipo_cliente'] === Organizacion::TIPO_SOCIO;
        $metrics ??= ['operations_count' => 0, 'service_amount' => 0.0, 'total_billed' => 0.0, 'paid' => 0.0, 'receivable' => 0.0, 'open_count' => 0, 'closed_count' => 0];
        $registered = $org->getAttribute('fecha_registro');
        $origin = $org->getRelationValue('paisOrigen');

        return [
            'id' => (int) $org->getKey(),
            'type' => $isPartner ? self::TYPE_PARTNER : self::TYPE_IMPORTER,
            'organization_id' => (int) $org->getKey(),
            'name' => (string) $org->getAttribute('No_Organizacion'),
            'country' => $origin instanceof \App\Models\Pais ? (string) $origin->getAttribute('No_Pais') : (string) ($data['pais_origen'] ?? ''),
            'origin_country_id' => $data['pais_origen_id'],
            'consolidation_country_ids' => $isPartner ? $data['paises_consolidar'] : [],
            'registered_on' => $registered instanceof \DateTimeInterface ? $registered->format('Y-m-d') : null,
            'whatsapp' => (string) ($data['whatsapp'] ?? ''),
            'contact_name' => (string) ($data['contacto'] ?? ''),
            'email' => (string) ($data['correo'] ?? ''),
            'rate_1' => $isPartner ? $data['tarifa_1'] : null,
            'rate_2' => $isPartner ? $data['tarifa_2'] : null,
            // Compatibilidad con la vista anterior (consol = tarifa 1, consol_2 = tarifa 2).
            'prices' => $isPartner ? ['consol' => $data['tarifa_1'], 'consol_2' => $data['tarifa_2']] : null,
            'operations_count' => (int) $metrics['operations_count'],
            'service_amount' => $metrics['service_amount'],
            'total_billed' => $metrics['total_billed'],
            'paid' => $metrics['paid'],
            'receivable' => $metrics['receivable'],
            'open_count' => (int) $metrics['open_count'],
            'closed_count' => (int) $metrics['closed_count'],
            'can_delete' => !$isHouse && !$isPartner && (int) $metrics['operations_count'] === 0,
            'is_house' => $isHouse,
            'can_download_balance' => true,
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
