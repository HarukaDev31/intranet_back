<?php

namespace App\Services\ChinaCaja;

use App\Exceptions\ChinaBillingException;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\ChinaCaja\ChinaContainerStage;
use App\Models\ChinaCaja\ChinaFreightPayment;
use App\Models\ChinaCaja\ChinaFreightPaymentItem;
use App\Models\ChinaCaja\ChinaShippingOption;
use App\Services\ChinaBilling\ChinaBillingCalculator;
use App\Services\ChinaBilling\ChinaBillingReader;
use App\Services\ChinaBilling\ChinaDraftService;
use App\Traits\FileTrait;
use App\Traits\UsesObjectStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Etapas del consolidado y Estado de contenedores: un mismo registro (china_container_stages).
 */
class ChinaContainerService
{
    use FileTrait;
    use UsesObjectStorage;

    private const TEXT_FIELDS = ['filling_on', 'sailing_on', 'arrival_on', 'shipping_line', 'destination_port', 'free_days',
        'container_type', 'shipper', 'bl_consignee', 'bl_description', 'bl_number'];

    private const FILES = ['bl_draft', 'bl_official'];

    /** @var array<string, Collection<int, array<string, mixed>>> */
    private array $memo = [];

    public function __construct(
        private readonly ChinaBillingReader $billing,
        private readonly ChinaDraftService $drafts,
    ) {
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(array $organizationIds): Collection
    {
        $key = implode(',', $organizationIds);

        return $this->memo[$key] ??= $this->build($organizationIds);
    }

    /**
     * Estado de contenedores: los que ya tienen fecha de llenado o están cerrados en China.
     *
     * @param  array<int, int>  $organizationIds
     * @return Collection<int, array<string, mixed>>
     */
    public function shipping(array $organizationIds): Collection
    {
        return $this->rows($organizationIds)
            ->filter(fn (array $row) => $row['data']['filling_on'] || $row['closed'])
            ->sortByDesc(fn (array $row) => $row['data']['filling_on'] ?: $row['closed_on'])
            ->values();
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @return array<string, mixed>
     */
    public function find(int $containerId, array $organizationIds): array
    {
        $row = $this->rows($organizationIds)->firstWhere('id', $containerId);
        if (!$row) {
            throw new ChinaBillingException('Contenedor no encontrado.', 404);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $files
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     * @return array<string, mixed>
     */
    public function update(int $containerId, array $data, array $files, array $actor): array
    {
        $row = $this->find($containerId, $actor['organization_ids']);
        $this->guardLocks($row, $data, $files);
        $values = [];
        foreach (self::TEXT_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $values[$field] = trim((string) $data[$field]) === '' ? null : trim((string) $data[$field]);
            }
        }
        foreach (['shipping_line', 'shipper', 'bl_consignee'] as $field) {
            if (!empty($values[$field])) {
                $values[$field] = mb_strtoupper($values[$field]);
            }
        }
        if (array_key_exists('freight_usd', $data)) {
            $this->guardFreight($containerId, $row, $data['freight_usd']);
            $values['freight_usd'] = $data['freight_usd'] === null || $data['freight_usd'] === '' ? null : round((float) $data['freight_usd'], 2);
        }
        foreach (['list_updated', 'telex'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $values[$flag] = filter_var($data[$flag], FILTER_VALIDATE_BOOLEAN);
            }
        }
        foreach (self::FILES as $kind) {
            if (!empty($data['remove_'.$kind])) {
                $values[$kind.'_path'] = null;
                $values[$kind.'_name'] = null;
            }
            if (!empty($files[$kind])) {
                $values[$kind.'_path'] = $this->storeFile($files[$kind], 'china-contenedores', $kind.'-'.$containerId);
                $values[$kind.'_name'] = $files[$kind]->getClientOriginalName();
            }
        }

        DB::transaction(function () use ($containerId, $values, $actor) {
            ChinaContainerStage::query()->updateOrCreate(['container_id' => $containerId], $values + ['updated_by' => $actor['id']]);
            foreach ([ChinaShippingOption::KIND_LINE => 'shipping_line', ChinaShippingOption::KIND_SHIPPER => 'shipper'] as $kind => $field) {
                if (!empty($values[$field])) {
                    ChinaShippingOption::query()->firstOrCreate(['kind' => $kind, 'name' => $values[$field]]);
                }
            }
        });
        $this->memo = [];

        return $this->find($containerId, $actor['organization_ids']);
    }

    /** @return array{lines: array<int, string>, shippers: array<int, string>, container_types: array<int, string>} */
    public function options(): array
    {
        $names = fn (string $kind) => ChinaShippingOption::query()->where('kind', $kind)->orderBy('name')->pluck('name')->map(fn ($name) => (string) $name);
        $lines = $names(ChinaShippingOption::KIND_LINE)->concat(['MAERSK', 'MSC', 'COSCO', 'HAPAG-LLOYD', 'CMA CGM', 'ONE'])->unique()->sort()->values()->all();

        return ['lines' => $lines, 'shippers' => $names(ChinaShippingOption::KIND_SHIPPER)->all(), 'container_types' => ChinaContainerRules::CONTAINER_TYPES];
    }

    /**
     * Un voucher puede pagar varios contenedores. Sin voucher solo si se confirma manualmente.
     *
     * @param  array<int, int>  $containerIds
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function payFreight(array $containerIds, string $paidOn, ?UploadedFile $voucher, bool $manual, float $rate, array $actor): ChinaFreightPayment
    {
        $containerIds = array_values(array_unique(array_map('intval', $containerIds)));
        if ($containerIds === []) {
            throw new ChinaBillingException('Selecciona al menos un contenedor.');
        }
        if (!$voucher && !$manual) {
            throw new ChinaBillingException('Adjunta el voucher o confirma el pago manualmente.');
        }
        $amounts = [];
        foreach ($containerIds as $id) {
            $row = $this->find($id, $actor['organization_ids']);
            if ($row['freight']['paid']) {
                throw new ChinaBillingException('El flete de '.$row['label'].' ya está pagado.', 409);
            }
            if ((float) $row['data']['freight_usd'] <= 0) {
                throw new ChinaBillingException($row['label'].' no tiene precio de flete.');
            }
            $amounts[$id] = (float) $row['data']['freight_usd'];
        }
        $path = $voucher ? $this->storeFile($voucher, 'china-contenedores', 'flete-'.$containerIds[0]) : null;

        $payment = DB::transaction(function () use ($amounts, $paidOn, $voucher, $path, $rate, $actor) {
            $taken = ChinaFreightPaymentItem::query()->whereIn('container_id', array_keys($amounts))->lockForUpdate()->exists();
            if ($taken) {
                throw new ChinaBillingException('Uno de los fletes ya está pagado.', 409);
            }
            $usd = array_sum($amounts);
            $payment = ChinaFreightPayment::query()->create([
                'paid_on' => $paidOn, 'manual' => $voucher === null, 'exchange_rate' => $rate, 'usd_amount' => round($usd, 2),
                'yuan_amount' => round($usd * $rate, 2), 'voucher_path' => $path, 'voucher_name' => $voucher?->getClientOriginalName(),
                'created_by' => $actor['id'],
            ]);
            foreach ($amounts as $containerId => $amount) {
                $payment->items()->create(['container_id' => $containerId, 'usd_amount' => $amount]);
            }

            return $payment;
        });
        $this->memo = [];

        return $payment;
    }

    public function freightPayment(int $paymentId): ChinaFreightPayment
    {
        $payment = ChinaFreightPayment::query()->with('items')->find($paymentId);
        if (!$payment) {
            throw new ChinaBillingException('Pago de flete no encontrado.', 404);
        }

        return $payment;
    }

    /** @param  array{organization_ids: array<int, int>}  $actor */
    public function cancelFreight(int $paymentId, array $actor): void
    {
        $payment = $this->freightPayment($paymentId);
        foreach ($payment->items as $item) {
            $this->find((int) $item->container_id, $actor['organization_ids']);
        }
        DB::transaction(function () use ($payment) {
            $payment->items()->delete();
            $payment->delete();
        });
        $this->memo = [];
    }

    /**
     * Guardar el archivo de un cierre, voucher o BL.
     */
    public function storeFile(UploadedFile $file, string $directory, string $prefix): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');

        return $this->storageFinalizeCdnPath($this->storageStoreUpload($file, $directory, $prefix.'-'.now()->format('YmdHisv').'.'.$extension));
    }

    public function url(?string $path): ?string
    {
        return $path ? $this->cdnStorageUrl($path) : null;
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @return Collection<int, array<string, mixed>>
     */
    private function build(array $organizationIds): Collection
    {
        $today = ChinaCashRules::today();
        $since = config('china_billing.borradores_desde');
        $containers = Contenedor::query()->withoutGlobalScopes()->with('pais')
            ->whereNull('deleted_at')
            ->whereIn('organizacion_id', $organizationIds)
            ->where(fn ($query) => $query->whereNull('empresa')->orWhere('empresa', '!=', '1'))
            // Los cerrados antes del inicio del flujo no se arrastran (igual que en Operaciones).
            ->where(fn ($query) => $query->whereNull('estado_china')
                ->orWhere('estado_china', '!=', Contenedor::CONTEDOR_CERRADO)
                ->when($since, fn ($inner, $date) => $inner->orWhereRaw('DATE(COALESCE(lista_embarque_uploaded_at, f_cierre)) >= ?', [$date]))
                ->when(!$since, fn ($inner) => $inner->orWhere('estado_china', Contenedor::CONTEDOR_CERRADO)))
            ->get();
        $ids = $containers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $stages = ChinaContainerStage::query()->whereIn('container_id', $ids)->get()->keyBy('container_id');
        $cbm = DB::table('contenedor_consolidado_cotizacion_proveedores as p')
            ->join('contenedor_consolidado_cotizacion as c', 'c.id', '=', 'p.id_cotizacion')
            ->whereIn('p.id_contenedor', $ids)->whereNull('c.deleted_at')->where('c.estado_cotizador', 'CONFIRMADO')
            ->groupBy('p.id_contenedor')->selectRaw('p.id_contenedor, COALESCE(SUM(p.cbm_total_china), 0) as cbm')->pluck('cbm', 'id_contenedor');
        $operations = $this->billing->operations($organizationIds, null, null)->whereNotNull('container_id')->keyBy('container_id');
        $drafts = $this->drafts->list($organizationIds)->keyBy('container_id');
        $freights = ChinaFreightPaymentItem::query()->with('payment')->whereIn('container_id', $ids)->get()->keyBy('container_id');
        $names = $this->billing->organizationNames($organizationIds);

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = $containers->toBase()->map(function (Contenedor $container) use ($today, $stages, $cbm, $operations, $drafts, $freights, $names) {
            $id = (int) $container->id;
            $data = $this->stageData($stages->get($id), $container);
            $closed = $container->estado_china === Contenedor::CONTEDOR_CERRADO;
            $operation = $operations->get($id);
            $metrics = $operation ? ChinaBillingCalculator::operationMetrics($operation) : null;
            $freight = $freights->get($id)?->payment;
            $collection = match (true) {
                !$metrics => 'none',
                in_array($metrics['status'], [ChinaBillingCalculator::STATUS_CONFIRMED, ChinaBillingCalculator::STATUS_IN_CASH], true) && $metrics['partner_status'] === 'paid' => 'paid',
                $metrics['confirmed'] > 0 => 'partial',
                default => 'pending',
            };

            return [
                'id' => $id,
                'label' => 'Consolidado #'.$container->cargaConParte(),
                'country' => (string) ($container->pais->No_Pais ?? ''),
                'organization_id' => (int) $container->organizacion_id,
                'client' => $names[(int) $container->organizacion_id]['name'] ?? (string) $container->empresa,
                'closed' => $closed,
                'closed_on' => $this->date($container->lista_embarque_uploaded_at ?: $container->f_cierre),
                'cbm' => round((float) ($cbm[$id] ?? 0), 3),
                'data' => $data,
                'bl_draft_url' => $this->url($data['bl_draft_path']),
                'bl_official_url' => $this->url($data['bl_official_path']),
                'operation' => $operation ? ['id' => (int) $operation->id, 'code' => $operation->code, 'status' => $metrics['status'], 'pending' => $metrics['pending']] : null,
                'operation_to_approve' => $drafts->has($id),
                'collection' => $collection,
                'freight' => [
                    'paid' => $freight !== null, 'payment_id' => $freight ? (int) $freight->id : null, 'paid_on' => $freight?->paid_on,
                    'manual' => (bool) ($freight->manual ?? false), 'voucher_name' => $freight?->voucher_name, 'voucher_url' => $this->url($freight?->voucher_path),
                ],
                'progress' => ChinaContainerRules::progress($data, (float) ($cbm[$id] ?? 0), $closed, $operation !== null, $today),
                'status' => ChinaContainerRules::status([
                    'sailing_on' => $data['sailing_on'], 'arrival_on' => $data['arrival_on'], 'has_bl' => $data['bl_official_path'] !== null,
                    'telex' => $data['telex'], 'collected' => $collection === 'paid', 'freight_paid' => $freight !== null,
                ], $today),
            ];
        })->values();

        return $rows;
    }

    /** @return array<string, mixed> */
    private function stageData(?ChinaContainerStage $stage, Contenedor $container): array
    {
        $data = [];
        foreach (self::TEXT_FIELDS as $field) {
            $data[$field] = $stage->{$field} ?? null;
        }
        foreach (self::FILES as $kind) {
            $data[$kind.'_path'] = $stage->{$kind.'_path'} ?? null;
            $data[$kind.'_name'] = $stage->{$kind.'_name'} ?? null;
        }
        $data['freight_usd'] = isset($stage->freight_usd) ? (float) $stage->freight_usd : null;
        $data['list_updated'] = (bool) ($stage->list_updated ?? false);
        $data['telex'] = (bool) ($stage->telex ?? false);
        // Mientras China no los escriba se muestran los que ya puso Documentación.
        $data['sailing_on'] ??= $this->date($container->fecha_zarpe);
        $data['arrival_on'] ??= $this->date($container->fecha_arribo);
        $data['shipping_line'] ??= $container->naviera ?: null;

        return $data;
    }

    private function date(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' || str_starts_with($text, '0000') ? null : CarbonImmutable::parse($text)->toDateString();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $files
     */
    private function guardLocks(array $row, array $data, array $files): void
    {
        $groups = [
            0 => array_keys(ChinaContainerRules::RESERVATION),
            1 => ['list_updated', 'bl_consignee', 'bl_description'],
            2 => ['bl_number', 'bl_draft', 'remove_bl_draft'],
        ];
        $sent = array_merge(array_keys($data), array_keys(array_filter($files)));
        foreach ($groups as $index => $fields) {
            $stage = $row['progress']['stages'][$index];
            // Un contenedor que ya está en Estado de contenedores se edita completo desde su panel.
            if ($stage['locked'] && !$row['closed'] && array_intersect($fields, $sent) !== []) {
                throw new ChinaBillingException($stage['lock_message'].'.', 409);
            }
        }
    }

    /** @param  array<string, mixed>  $row */
    private function guardFreight(int $containerId, array $row, mixed $value): void
    {
        if ($row['freight']['paid'] && abs((float) $value - (float) $row['data']['freight_usd']) > 0.004) {
            throw new ChinaBillingException('El flete ya está pagado: anula el pago para cambiar el precio.', 409);
        }
    }
}
