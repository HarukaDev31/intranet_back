<?php

namespace App\Services\ChinaCaja;

use App\Models\ChinaBilling\ChinaPayment;
use App\Models\ChinaCaja\ChinaFixedCost;
use App\Models\ChinaCaja\ChinaFixedCostCharge;
use App\Models\ChinaCaja\ChinaFreightPaymentItem;
use App\Models\ChinaCaja\ChinaInvestment;
use App\Models\PagoProveedor\PagoProveedorEjecucion;
use App\Models\PagoProveedor\PagoProveedorIngreso;
use App\Models\PagoProveedor\PagoProveedorSolicitud;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Services\ChinaBilling\ChinaBillingCalculator;
use App\Services\ChinaBilling\ChinaBillingReader;
use App\Services\PagoProveedor\PagoProveedorYuanService;
use Carbon\CarbonImmutable;

/**
 * Junta en RMB lo que entra y sale: cobros en caja, pagos a proveedor, fletes, costos fijos e inversiones.
 */
class ChinaCashMovements
{
    public const OPERATION = 'Operación';

    public const SUPPLIER = 'Pago proveedor';

    public const FREIGHT = 'Flete';

    public const FIXED = 'Costo fijo';

    public const INVESTMENT = 'Inversión';

    public function __construct(
        private readonly ChinaBillingReader $billing,
        private readonly ChinaContainerService $containers,
        private readonly PagoProveedorYuanService $yuan,
    ) {
    }

    /**
     * Movimientos reales entre dos fechas (incluidas).
     *
     * @param  array<int, int>  $organizationIds
     * @return list<array<string, mixed>>
     */
    public function real(array $organizationIds, string $from, string $to): array
    {
        $names = $this->billing->organizationNames($organizationIds);
        $out = [];
        $payments = ChinaPayment::query()->with('allocations.operation')->whereIn('organization_id', $organizationIds)
            ->whereNotNull('cashed_at')->whereBetween('cashed_at', $this->utcRange($from, $to))->get();
        foreach ($payments as $payment) {
            $out[] = $this->row($this->chinaDate($payment->cashed_at), 'in', self::OPERATION,
                'Cobro '.$payment->code.' · '.$payment->allocations->map(fn ($a) => $a->operation->code ?? '')->filter()->implode(', '),
                $names[(int) $payment->organization_id]['name'] ?? '', (float) $payment->yuan_amount, true, 'payment', (int) $payment->id, $names[(int) $payment->organization_id]['name'] ?? null);
        }
        // Pago a proveedor es de todo el negocio (el Encargado China ve todas las solicitudes).
        $incomes = PagoProveedorIngreso::query()->with('solicitud.proveedores', 'solicitud.organizacion')->whereBetween('received_on', [$from, $to])->get();
        foreach ($incomes as $income) {
            $out[] = $this->row($income->received_on->toDateString(), 'in', self::SUPPLIER, 'Ingreso socio · '.$this->requestCode($income->solicitud),
                'USD '.number_format($income->usd_amount, 2).' × TC '.number_format($income->exchange_rate, 4), $income->yuan_amount, true, 'request', (int) $income->request_id, $this->requestClient($income->solicitud));
        }
        // Una fila por solicitud y día, con cuántos proveedores se pagaron.
        $executions = PagoProveedorEjecucion::query()->with('proveedor.solicitud.proveedores', 'proveedor.solicitud.organizacion')
            ->whereNotNull('yuan_paid')->whereBetween('executed_on', [$from, $to])->get()->filter(fn (PagoProveedorEjecucion $e) => $e->proveedor !== null);
        foreach ($executions->groupBy(fn (PagoProveedorEjecucion $e) => $e->proveedor->request_id.'|'.$e->executed_on->toDateString()) as $group) {
            $request = $group->first()->proveedor->solicitud;
            $count = $group->pluck('request_provider_id')->unique()->count();
            $out[] = $this->row($group->first()->executed_on->toDateString(), 'out', self::SUPPLIER, 'Pago a proveedores · '.$this->requestCode($request),
                $count.($count === 1 ? ' proveedor' : ' proveedores'), (float) $group->sum('yuan_paid'), true, 'request', (int) $group->first()->proveedor->request_id, $this->requestClient($request));
        }
        $items = ChinaFreightPaymentItem::query()->with('payment')
            ->whereHas('payment', fn ($query) => $query->whereBetween('paid_on', [$from, $to]))->get();
        $labels = $this->containers->rows($organizationIds)->pluck('label', 'id');
        foreach ($items as $item) {
            $out[] = $this->row($item->payment->paid_on, 'out', self::FREIGHT, 'Flete '.($labels[(int) $item->container_id] ?? '#'.$item->container_id),
                'USD '.number_format((float) $item->usd_amount, 2).' × TC '.$item->payment->exchange_rate, $item->usd_amount * $item->payment->exchange_rate, true, 'container', (int) $item->container_id);
        }
        foreach (ChinaFixedCostCharge::query()->whereBetween('charged_on', [$from, $to])->get() as $charge) {
            $out[] = $this->row((string) $charge->charged_on, 'out', self::FIXED, (string) $charge->name, 'Descuento automático de fin de mes', (float) $charge->amount, true);
        }
        foreach (ChinaInvestment::query()->whereNotNull('paid_on')->whereBetween('paid_on', [$from, $to])->get() as $investment) {
            $out[] = $this->row((string) $investment->paid_on, 'out', self::INVESTMENT, (string) $investment->name, 'Inversión anual', (float) $investment->amount, true);
        }

        return collect($out)->sortByDesc('date')->values()->all();
    }

    /**
     * Lo que falta entrar o salir. `due_on` null = sin fecha límite; `late` = pasó la fecha.
     *
     * @param  array<int, int>  $organizationIds
     * @return list<array<string, mixed>>
     */
    public function projected(array $organizationIds, float $rate, CarbonImmutable $today): array
    {
        $out = [];
        $names = $this->billing->organizationNames($organizationIds);
        $containers = $this->containers->rows($organizationIds)->keyBy('id');
        foreach ($this->billing->operations($organizationIds, null, null) as $operation) {
            $container = $operation->container_id ? $containers->get($operation->container_id) : null;
            $arrival = $container['data']['arrival_on'] ?? null;
            $metrics = ChinaBillingCalculator::operationMetrics($operation);
            if (!$arrival || $metrics['status'] !== ChinaBillingCalculator::STATUS_PENDING || $metrics['pending'] <= 0) {
                continue;
            }
            $out[] = $this->pending(ChinaCashRules::dueBeforeArrival($arrival), $today, 'in', self::OPERATION, 'Cobro '.$operation->code.' · '.$container['label'],
                $names[(int) $operation->organization_id]['name'] ?? '', $metrics['pending'] * $rate, 'operation', (int) $operation->id, $names[(int) $operation->organization_id]['name'] ?? null, $arrival);
        }
        foreach ($containers as $container) {
            $arrival = $container['data']['arrival_on'];
            if ($arrival && !$container['freight']['paid'] && (float) $container['data']['freight_usd'] > 0) {
                $out[] = $this->pending(ChinaCashRules::dueBeforeArrival($arrival), $today, 'out', self::FREIGHT, 'Flete '.$container['label'],
                    $container['country'], (float) $container['data']['freight_usd'] * $rate, 'container', $container['id'], null, $arrival);
            }
        }
        foreach ($this->yuan->list(['bucket' => 'all', 'per_page' => 100000])->items() as $group) {
            $summary = $this->yuan->summary($group);
            $code = $group['providers']->map(fn (PagoProveedorSolicitudProveedor $p) => $p->request_code)->filter()->unique()->implode(' / ');
            $client = (string) ($group['request']->client_name ?: $group['request']->partner);
            $partner = $this->requestClient($group['request']);
            if ($summary['yuan_pending'] > 0) {
                $out[] = $this->pending(null, $today, 'out', self::SUPPLIER, 'Pago a proveedores · '.$code, $client, $summary['yuan_pending'], 'request', (int) $group['request']->id, $partner);
            }
            if ($summary['difference'] < -0.005) {
                $out[] = $this->pending(null, $today, 'in', self::SUPPLIER, 'Ingreso esperado socio · '.$code, $client, abs($summary['difference']), 'request', (int) $group['request']->id, $partner);
            }
        }
        $month = $today->format('Y-m');
        $charged = ChinaFixedCostCharge::query()->where('month', $month)->pluck('fixed_cost_id')->all();
        $monthEnd = $today->endOfMonth()->startOfDay();
        foreach (ChinaFixedCost::query()->where('active', true)->whereNotIn('id', $charged)->get() as $cost) {
            $out[] = $this->pending($monthEnd, $today, 'out', self::FIXED, (string) $cost->name, 'Se descuenta el último día del mes', (float) $cost->amount);
        }
        foreach (ChinaInvestment::query()->whereNull('paid_on')->get() as $investment) {
            $out[] = $this->pending(CarbonImmutable::parse((string) $investment->due_on), $today, 'out', self::INVESTMENT, (string) $investment->name, 'Inversión anual', (float) $investment->amount, 'investment', (int) $investment->id);
        }

        return collect($out)->sortBy('date')->values()->all();
    }

    /**
     * Cobros confirmados en USD que aún no se cambian a RMB.
     *
     * @param  array<int, int>  $organizationIds
     * @return list<array<string, mixed>>
     */
    public function usdToExchange(array $organizationIds, ?string $until = null): array
    {
        $names = $this->billing->organizationNames($organizationIds);

        return ChinaPayment::query()->with('allocations.operation')->whereIn('organization_id', $organizationIds)
            ->whereNotNull('confirmed_at')->whereNull('cashed_at')
            ->when($until, fn ($query, $date) => $query->where('confirmed_at', '<=', $this->utcRange($date, $date)[1]))
            ->orderBy('confirmed_at')->get()
            ->map(fn (ChinaPayment $payment) => [
                'id' => (int) $payment->id, 'code' => $payment->code, 'client' => $names[(int) $payment->organization_id]['name'] ?? '',
                'operations' => $payment->allocations->map(fn ($a) => $a->operation->code ?? '')->filter()->values()->all(),
                'confirmed_on' => $this->chinaDate($payment->confirmed_at), 'usd' => (float) $payment->amount_received,
            ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function pending(?CarbonImmutable $due, CarbonImmutable $today, string $type, string $origin, string $title, string $detail, float $amount, ?string $link = null, ?int $linkId = null, ?string $client = null, ?string $arrival = null): array
    {
        // Lo que ya venció sigue pendiente: se proyecta para hoy.
        $date = $due === null || $due->lt($today) ? $today : $due;
        $days = $due === null ? null : (int) $today->diffInDays($due, false);

        return $this->row($date->toDateString(), $type, $origin, $title, $detail, $amount, false, $link, $linkId, $client)
            // Atrasados: solo cobros y fletes (vencen 7 días antes del arribo).
            + ['due_on' => $due?->toDateString(), 'arrival_on' => $arrival, 'days' => $days, 'late' => $arrival !== null && $days !== null && $days < 0];
    }

    /** @return array<string, mixed> */
    private function row(string $date, string $type, string $origin, string $title, string $detail, float $amount, bool $real, ?string $link = null, ?int $linkId = null, ?string $client = null): array
    {
        return ['date' => $date, 'type' => $type, 'origin' => $origin, 'title' => $title, 'detail' => $detail,
            'amount' => round($amount, 2), 'real' => $real, 'link' => $link, 'link_id' => $linkId, 'client' => $client ?: null];
    }

    private function requestCode(?PagoProveedorSolicitud $request): string
    {
        return $request ? $request->proveedores->pluck('request_code')->filter()->unique()->implode(' / ') : '';
    }

    /** Socio que envía los dólares. */
    private function requestClient(?PagoProveedorSolicitud $request): ?string
    {
        return $request ? ($request->organizacion->No_Organizacion ?? $request->partner) : null;
    }

    private function chinaDate(mixed $timestamp): string
    {
        return CarbonImmutable::parse($timestamp, 'UTC')->setTimezone(ChinaCashRules::TIMEZONE)->toDateString();
    }

    /** @return array{0: string, 1: string} */
    private function utcRange(string $from, string $to): array
    {
        return [
            CarbonImmutable::parse($from, ChinaCashRules::TIMEZONE)->startOfDay()->utc()->toDateTimeString(),
            CarbonImmutable::parse($to, ChinaCashRules::TIMEZONE)->endOfDay()->utc()->toDateTimeString(),
        ];
    }
}
