<?php

namespace App\Services\ChinaBilling;

use App\Models\CargaConsolidada\Contenedor;
use App\Models\ChinaBilling\ChinaOperation;
use App\Models\ChinaBilling\ChinaPayment;
use App\Traits\FileTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Lecturas de Operaciones/Cobros. Todo número sale de ChinaBillingCalculator.
 */
class ChinaBillingReader
{
    use FileTrait;

    public function __construct(
        private readonly ChinaDraftService $drafts,
        private readonly ChinaInvoiceState $invoiceState,
    )
    {
    }

    /**
     * Vista China: operaciones + borradores + KPIs + saldos a favor.
     *
     * @param  array<int, int>  $organizationIds
     * @param  array<string, mixed>  $filters  search, status, country_id, organization_id, from, to, page, per_page
     * @return array<string, mixed>
     */
    public function warehouseOperations(array $organizationIds, array $filters): array
    {
        $organizationIds = $this->scopeOrganizations($organizationIds, $filters);
        $operations = $this->operations($organizationIds, $filters['from'] ?? null, $filters['to'] ?? null);
        $payments = $this->payments($organizationIds, $filters['from'] ?? null, $filters['to'] ?? null);
        $names = $this->organizationNames($organizationIds);

        $rows = $operations->map(fn (ChinaOperation $op) => $this->presentOperation($op, ChinaBillingCalculator::operationMetrics($op), $names));
        $drafts = $this->drafts->list($organizationIds)
            ->filter(fn (array $draft) => $this->inRange($draft['issued_on'], $filters['from'] ?? null, $filters['to'] ?? null))
            ->map(fn (array $draft) => $this->presentDraft($draft, $names));

        $all = $rows->concat($drafts)->sortByDesc('issued_on')->values();
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $status = (string) ($filters['status'] ?? '');

        $operationsTotal = $this->operations($organizationIds, null, null)->count();
        $counts = [
            'all' => $all->count(),
            'draft' => $drafts->count(),
            // Sin rango, búsqueda ni estado: para los contadores "N de M" y de la pestaña.
            'operations_total' => $operationsTotal,
            'overall' => $operationsTotal + $this->drafts->list($organizationIds)->count(),
        ];
        foreach ([ChinaBillingCalculator::STATUS_PENDING, ChinaBillingCalculator::STATUS_TO_CONFIRM, ChinaBillingCalculator::STATUS_CONFIRMED, ChinaBillingCalculator::STATUS_IN_CASH] as $key) {
            $counts[$key] = $rows->where('status', $key)->count();
        }

        $filtered = $all->filter(function (array $row) use ($search, $status) {
            if ($status !== '' && $status !== 'all' && $row['status'] !== $status) {
                return false;
            }

            return $search === '' || str_contains(mb_strtolower(implode(' ', [
                $row['code'], $row['organization']['name'], $row['description'], $row['organization']['country'], $row['service_name'],
            ])), $search);
        })->values();

        $page = $this->paginate($filtered, $filters);
        $invoices = $this->invoiceState->forRows($page->items());
        $page->setCollection($page->getCollection()->map(
            fn (array $row) => empty($row['is_draft']) ? $row + ['invoice' => $invoices[(int) $row['id']] ?? null] : $row
        ));

        return [
            'page' => $page,
            'counts' => $counts,
            'summary' => ChinaBillingCalculator::summary($operations, $payments),
            'credits' => $this->creditsByOrganization($organizationIds, $names),
            'organizations' => $this->organizationOptions($organizationIds),
        ];
    }

    /**
     * KPIs del encabezado (operaciones y pagos del rango) y saldos a favor de los clientes.
     *
     * @param  array<int, int>  $organizationIds
     * @param  array<string, mixed>  $filters  from, to
     * @return array<string, mixed>
     */
    public function warehouseSummary(array $organizationIds, array $filters): array
    {
        $operations = $this->operations($organizationIds, $filters['from'] ?? null, $filters['to'] ?? null);
        $payments = $this->payments($organizationIds, $filters['from'] ?? null, $filters['to'] ?? null);

        return [
            'summary' => ChinaBillingCalculator::summary($operations, $payments),
            'credits' => $this->creditsByOrganization($organizationIds, $this->organizationNames($organizationIds)),
        ];
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function warehousePayments(array $organizationIds, array $filters): array
    {
        $organizationIds = $this->scopeOrganizations($organizationIds, $filters);
        $payments = $this->payments($organizationIds, $filters['from'] ?? null, $filters['to'] ?? null);
        $names = $this->organizationNames($organizationIds);
        $rows = $payments->map(fn (ChinaPayment $payment) => $this->presentPayment($payment, $names))->values();

        $status = (string) ($filters['status'] ?? '');
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $counts = ['all' => $rows->count(), 'overall' => $this->payments($organizationIds, null, null)->count()];
        foreach ([ChinaBillingCalculator::STATUS_TO_CONFIRM, ChinaBillingCalculator::STATUS_CONFIRMED, ChinaBillingCalculator::STATUS_IN_CASH] as $key) {
            $counts[$key] = $rows->where('status', $key)->count();
        }

        $filtered = $rows->filter(fn (array $row) => ($status === '' || $status === 'all' || $row['status'] === $status)
            && ($search === '' || str_contains(mb_strtolower(implode(' ', [
                $row['code'], $row['organization']['name'], $row['organization']['country'], implode(' ', array_column($row['allocations'], 'code')),
            ])), $search)))->values();

        return ['page' => $this->paginate($filtered, $filters), 'counts' => $counts];
    }

    /**
     * Vista Socio: facturas (operaciones aprobadas), pagos y KPIs de su organización.
     *
     * @param  array<int, int>  $organizationIds
     * @param  array<string, mixed>  $filters  status (all|due|to_confirm|paid)
     * @return array<string, mixed>
     */
    public function partnerInvoices(array $organizationIds, array $filters): array
    {
        $operations = $this->operations($organizationIds, null, null);
        $payments = $this->payments($organizationIds, null, null);
        $names = $this->organizationNames($organizationIds);
        $rows = $operations->map(fn (ChinaOperation $op) => $this->presentOperation($op, ChinaBillingCalculator::operationMetrics($op), $names))->values();

        $filter = (string) ($filters['status'] ?? 'all');
        $filtered = $rows->filter(fn (array $row) => match ($filter) {
            'due' => $row['balance_due'] > 0,
            'to_confirm' => $row['partner_status'] === 'to_confirm',
            'paid' => $row['partner_status'] === 'paid',
            default => true,
        })->values();

        return [
            'page' => $this->paginate($filtered, $filters),
            'counts' => [
                'all' => $rows->count(),
                'due' => $rows->where('balance_due', '>', 0)->count(),
                'to_confirm' => $rows->where('partner_status', 'to_confirm')->count(),
                'paid' => $rows->where('partner_status', 'paid')->count(),
            ],
            'summary' => ChinaBillingCalculator::summary($operations, $payments),
        ];
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function partnerPayments(array $organizationIds, array $filters): array
    {
        $payments = $this->payments($organizationIds, null, null);
        $names = $this->organizationNames($organizationIds);
        $rows = $payments->map(fn (ChinaPayment $payment) => $this->presentPayment($payment, $names))->values();

        return ['page' => $this->paginate($rows, $filters)];
    }

    /**
     * Balance por organización: operaciones con saldo por pagar.
     *
     * @return array<string, mixed>
     */
    public function balance(int $organizationId): array
    {
        $names = $this->organizationNames([$organizationId]);
        $rows = $this->operations([$organizationId], null, null)
            ->map(fn (ChinaOperation $op) => $this->presentOperation($op, ChinaBillingCalculator::operationMetrics($op), $names))
            ->filter(fn (array $row) => $row['balance_due'] > 0)
            ->values();

        return [
            'organization' => ['id' => $organizationId, 'name' => $names[$organizationId]['name'] ?? ''],
            'rows' => $rows,
            'total' => ChinaBillingCalculator::round((float) $rows->sum('balance_due')),
            'issued_on' => now()->toDateString(),
        ];
    }

    /** @return Collection<int, ChinaOperation> */
    public function operations(array $organizationIds, ?string $from, ?string $to): Collection
    {
        return ChinaOperation::query()
            ->with(['costs', 'allocations.payment.creditApplications', 'creditApplications.payment'])
            ->whereIn('organization_id', $organizationIds)
            ->when($from, fn ($q) => $q->whereDate('issued_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('issued_on', '<=', $to))
            ->orderByDesc('issued_on')->orderByDesc('id')
            ->get();
    }

    /** @return Collection<int, ChinaPayment> */
    public function payments(array $organizationIds, ?string $from, ?string $to): Collection
    {
        return ChinaPayment::query()
            ->with(['allocations.operation', 'creditApplications'])
            ->whereIn('organization_id', $organizationIds)
            ->when($from, fn ($q) => $q->whereDate('paid_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_on', '<=', $to))
            ->orderByDesc('paid_on')->orderByDesc('id')
            ->get();
    }

    /**
     * Operación con su estado calculado (detalle, drawer, respuestas de mutaciones).
     *
     * @return array<string, mixed>
     */
    public function operationDetail(int $id): array
    {
        $op = ChinaOperation::query()->with(['costs', 'allocations.payment.creditApplications', 'creditApplications.payment'])->findOrFail($id);

        return $this->presentOperation($op, ChinaBillingCalculator::operationMetrics($op), $this->organizationNames([(int) $op->organization_id]));
    }

    /** @return array<string, mixed> */
    public function paymentDetail(int $id): array
    {
        $payment = ChinaPayment::query()->with(['allocations.operation', 'creditApplications'])->findOrFail($id);

        return $this->presentPayment($payment, $this->organizationNames([(int) $payment->organization_id]));
    }

    /**
     * @param  array<int, array{name: string, country: string}>  $names
     * @return array<string, mixed>
     */
    public function presentOperation(ChinaOperation $op, array $metrics, array $names): array
    {
        $org = $names[$op->organization_id] ?? ['name' => '', 'country' => ''];
        $transferredCode = $op->transferred_to_operation_id
            ? ChinaOperation::query()->whereKey($op->transferred_to_operation_id)->value('code')
            : null;

        return array_merge([
            'id' => (int) $op->id,
            'is_draft' => false,
            'code' => $op->code,
            'organization' => ['id' => (int) $op->organization_id, 'name' => $org['name'], 'country' => $org['country']],
            'issued_on' => $op->issued_on?->toDateString(),
            'description' => $op->description,
            'service_name' => $op->service_name,
            'billing_type' => $op->billing_type,
            'variant_name' => $op->variant_name,
            'container_id' => $op->container_id,
            'consolidado_label' => $this->consolidadoLabel($op->container_id),
            'cbm' => $op->cbm,
            'rate' => $op->rate,
            'rate_tier' => $op->rate_tier,
            'amount' => $op->amount,
            'fob' => $op->fob,
            'freight' => $op->freight,
            'packing_list_url' => $op->packing_list_path ? $this->cdnStorageUrl($op->packing_list_path) : null,
            'costs' => $op->costs->map(fn ($cost) => [
                'id' => (int) $cost->id, 'name' => $cost->name, 'amount' => (float) $cost->amount,
                'source_operation_id' => $cost->source_operation_id,
            ])->values()->all(),
            'transferred_to_operation_id' => $op->transferred_to_operation_id,
            'transferred_to_code' => $transferredCode,
            'payments' => $op->allocations->map(fn ($allocation) => [
                'payment_id' => (int) $allocation->payment_id,
                'code' => $allocation->payment->code,
                'amount' => (float) $allocation->amount,
                'status' => ChinaBillingCalculator::paymentStatus($allocation->payment),
                'paid_on' => $allocation->payment->paid_on?->toDateString(),
                'receipt_name' => $allocation->payment->receipt_name,
                'amount_sent' => (float) $allocation->payment->amount_sent,
            ])->values()->all(),
            'credits' => $op->creditApplications->map(fn ($credit) => [
                'id' => (int) $credit->id,
                'amount' => (float) $credit->amount,
                'from_operation_id' => $credit->from_operation_id,
                'payment_code' => $credit->payment ? $credit->payment->code : null,
                'applied_on' => $credit->applied_on ? $credit->applied_on->toDateString() : null,
            ])->values()->all(),
            'transferred_amount' => $this->transferredAmount($op),
            'balance_due' => $metrics['partner_balance'],
        ], $metrics);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array<int, array{name: string, country: string}>  $names
     * @return array<string, mixed>
     */
    private function presentDraft(array $draft, array $names): array
    {
        $org = $names[$draft['organization_id']] ?? ['name' => '', 'country' => ''];

        return [
            'id' => null,
            'is_draft' => true,
            'code' => null,
            'organization' => ['id' => $draft['organization_id'], 'name' => $org['name'], 'country' => $org['country']],
            'issued_on' => $draft['issued_on'],
            'description' => $draft['description'],
            'service_name' => 'Consolidado',
            'billing_type' => ChinaOperation::BILLING_CBM,
            'container_id' => $draft['container_id'],
            'cbm' => $draft['cbm'],
            'status' => 'draft',
            'total' => 0.0,
        ];
    }

    /**
     * @param  array<int, array{name: string, country: string}>  $names
     * @return array<string, mixed>
     */
    public function presentPayment(ChinaPayment $payment, array $names): array
    {
        $org = $names[$payment->organization_id] ?? ['name' => '', 'country' => ''];
        $status = ChinaBillingCalculator::paymentStatus($payment);

        return [
            'id' => (int) $payment->id,
            'code' => $payment->code,
            'organization' => ['id' => (int) $payment->organization_id, 'name' => $org['name'], 'country' => $org['country']],
            'paid_on' => $payment->paid_on?->toDateString(),
            'status' => $status,
            'registered_by' => $payment->registered_by,
            'amount_sent' => $payment->amount_sent,
            'amount_received' => $payment->amount_received,
            'allocated' => ChinaBillingCalculator::allocatedTotal($payment),
            'shortfall' => ChinaBillingCalculator::paymentShortfall($payment),
            'credit_generated' => ChinaBillingCalculator::paymentCredit($payment),
            'credit_used' => ChinaBillingCalculator::round((float) $payment->creditApplications->sum('amount')),
            'shortfall_operation_id' => $payment->shortfall_operation_id,
            'exchange_rate' => $payment->exchange_rate,
            'yuan_amount' => $payment->yuan_amount,
            'receipt_url' => $payment->receipt_path ? $this->cdnStorageUrl($payment->receipt_path) : null,
            'receipt_name' => $payment->receipt_name,
            'cash_voucher_url' => $payment->cash_voucher_path ? $this->cdnStorageUrl($payment->cash_voucher_path) : null,
            'cash_voucher_name' => $payment->cash_voucher_name,
            'confirmed_at' => $payment->confirmed_at?->toIso8601String(),
            'cashed_at' => $payment->cashed_at?->toIso8601String(),
            'unassigned' => ChinaBillingCalculator::round(max(0, (float) $payment->amount_sent - ChinaBillingCalculator::allocatedTotal($payment))),
            'allocations' => $payment->allocations->map(fn ($allocation) => [
                'operation_id' => (int) $allocation->operation_id,
                'code' => $allocation->operation->code,
                'label' => $this->allocationLabel($allocation->operation),
                'amount' => (float) $allocation->amount,
                // Lo que realmente cuenta para la operación: el faltante se descuenta de la elegida al confirmar.
                'real_amount' => ChinaBillingCalculator::round(
                    (int) $payment->shortfall_operation_id === (int) $allocation->operation_id
                        ? max(0, (float) $allocation->amount - ChinaBillingCalculator::paymentShortfall($payment))
                        : (float) $allocation->amount
                ),
            ])->values()->all(),
        ];
    }

    /** Importe que se pasó a otra orden; queda en gris en la columna Diferencia. */
    private function transferredAmount(ChinaOperation $op): ?float
    {
        if (!$op->transferred_to_operation_id) {
            return null;
        }
        $amount = \App\Models\ChinaBilling\ChinaOperationCost::query()
            ->where('source_operation_id', $op->id)
            ->where('operation_id', $op->transferred_to_operation_id)
            ->value('amount');

        return $amount !== null ? (float) $amount : null;
    }

    /** «Consolidado #46» del consolidado de la operación, si viene de uno. */
    private function consolidadoLabel(?int $containerId): ?string
    {
        if (!$containerId) {
            return null;
        }
        $container = Contenedor::query()->withoutGlobalScopes()->find($containerId);

        return $container ? 'Consolidado #'.$container->cargaConParte() : null;
    }

    /**
     * Etiqueta de la operación en el cobro (maqueta): servicio · N.° de consolidado · producto.
     * Ej.: «Consolidado · Consolidado #46 · herramientas eléctricas».
     */
    private function allocationLabel(ChinaOperation $op): string
    {
        $service = trim((string) $op->service_name);
        $consolidado = trim((string) $this->consolidadoLabel($op->container_id));
        $description = trim((string) $op->description);
        foreach (array($service, $consolidado) as $known) {
            if ($known === '') {
                continue;
            }
            $description = trim((string) preg_replace('/'.preg_quote($known, '/').'/iu', '', $description));
        }
        $description = trim((string) preg_replace('/(?:\s*[·\-]\s*)+/u', ' · ', $description), " ·\t");

        $parts = array();
        foreach (array($service, $consolidado, $description) as $part) {
            $part = trim((string) $part, " ·\t");
            if ($part !== '' && !in_array($part, $parts, true)) {
                $parts[] = $part;
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * Saldo a favor por organización, solo las que tienen.
     *
     * @param  array<int, int>  $organizationIds
     * @param  array<int, array{name: string, country: string}>  $names
     * @return array<int, array<string, mixed>>
     */
    public function creditsByOrganization(array $organizationIds, array $names): array
    {
        $payments = $this->payments($organizationIds, null, null)->groupBy('organization_id');

        return $payments->map(fn (Collection $group, $organizationId) => [
            'organization_id' => (int) $organizationId,
            'name' => $names[(int) $organizationId]['name'] ?? '',
            'balance' => ChinaBillingCalculator::creditBalance($group),
        ])->filter(fn (array $row) => $row['balance'] > 0)->values()->all();
    }

    /**
     * Clientes (organizaciones) para «Nueva operación»: tipo, país de origen, países donde consolidan y tarifas del socio.
     *
     * @param  array<int, int>  $organizationIds
     * @return array<int, array<string, mixed>>
     */
    public function organizationOptions(array $organizationIds): array
    {
        $ids = $organizationIds ?: [0];
        $clients = DB::table('organizacion as o')
            ->leftJoin('pais as p', 'p.ID_Pais', '=', 'o.pais_origen_id')
            ->whereIn('o.ID_Organizacion', $ids)
            ->get(['o.ID_Organizacion as id', 'o.tipo_cliente', 'o.pais_origen_id', 'p.No_Pais as origin_country', 'o.tarifa_1', 'o.tarifa_2'])
            ->keyBy('id');
        $enabled = DB::table('organizacion_paises_habilitados')->whereIn('organizacion_id', $ids)->orderBy('id')->get(['organizacion_id', 'id_pais'])
            ->groupBy('organizacion_id');

        // Los clientes eliminados de China (no facturables) no se ofrecen para operaciones nuevas.
        $removed = DB::table('organizacion')->whereIn('ID_Organizacion', $ids)->where('facturable_china', false)->pluck('ID_Organizacion')
            ->map(fn ($id) => (int) $id)->all();

        return collect(array_diff_key($this->organizationNames($organizationIds), array_flip($removed)))
            ->map(function (array $row, int $id) use ($clients, $enabled) {
                $client = $clients->get($id);
                $isPartner = ($client->tipo_cliente ?? 'socio') !== 'importador';
                $rate1 = $isPartner && isset($client->tarifa_1) ? (float) $client->tarifa_1 : null;
                $rate2 = $isPartner && isset($client->tarifa_2) ? (float) $client->tarifa_2 : null;

                return [
                    'id' => $id, 'name' => $row['name'], 'country' => $row['country'], 'country_id' => $row['country_id'],
                    'type' => $isPartner ? 'partner' : 'importer',
                    'origin_country_id' => isset($client->pais_origen_id) ? (int) $client->pais_origen_id : null,
                    'origin_country' => (string) ($client->origin_country ?? ''),
                    'consolidation_country_ids' => $isPartner
                        ? ($enabled->get($id) ?? collect())->pluck('id_pais')->map(fn ($v) => (int) $v)->values()->all()
                        : [],
                    'rate_1' => $rate1,
                    'rate_2' => $rate2,
                    // Tarifas del socio: precargan la tarifa de la operación (consol = tarifa 1, consol_2 = tarifa 2).
                    'prices' => $isPartner ? ['consol' => $rate1, 'consol_2' => $rate2] : null,
                ];
            })
            ->sortBy('name')->values()->all();
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @return array<int, array{name: string, country: string, country_id: int|null}>
     */
    public function organizationNames(array $organizationIds): array
    {
        return DB::table('organizacion as o')
            ->leftJoin('pais as p', 'p.ID_Pais', '=', 'o.id_pais')
            ->whereIn('o.ID_Organizacion', $organizationIds)
            ->get(['o.ID_Organizacion as id', 'o.No_Organizacion as name', 'p.No_Pais as country', 'o.id_pais as country_id'])
            ->mapWithKeys(fn ($row) => [(int) $row->id => [
                'name' => (string) $row->name, 'country' => (string) ($row->country ?? ''), 'country_id' => $row->country_id ? (int) $row->country_id : null,
            ]])->all();
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @param  array<string, mixed>  $filters
     * @return array<int, int>
     */
    private function scopeOrganizations(array $organizationIds, array $filters): array
    {
        $names = null;
        if (!empty($filters['organization_id'])) {
            $organizationIds = array_values(array_intersect($organizationIds, [(int) $filters['organization_id']]));
        }
        if (!empty($filters['country_id'])) {
            $names = $this->organizationNames($organizationIds);
            $organizationIds = array_values(array_filter(
                $organizationIds,
                fn (int $id) => ($names[$id]['country_id'] ?? null) === (int) $filters['country_id']
            ));
        }

        return $organizationIds;
    }

    private function inRange(string $date, ?string $from, ?string $to): bool
    {
        return (!$from || $date >= $from) && (!$to || $date <= $to);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function paginate(Collection $rows, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 15)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page);
    }
}
