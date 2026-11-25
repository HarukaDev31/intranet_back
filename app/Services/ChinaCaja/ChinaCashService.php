<?php

namespace App\Services\ChinaCaja;

use App\Exceptions\ChinaBillingException;
use App\Models\ChinaCaja\ChinaCashMonth;
use App\Models\ChinaCaja\ChinaCashWeek;
use App\Models\ChinaCaja\ChinaFixedCost;
use App\Models\ChinaCaja\ChinaFixedCostCharge;
use App\Models\ChinaCaja\ChinaInvestment;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Flujo de caja: todo en RMB. El saldo sale de los cierres, nunca de sumar movimientos.
 */
class ChinaCashService
{
    public function __construct(
        private readonly ChinaCashMovements $movements,
        private readonly ChinaContainerService $files,
    ) {
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @return array<string, mixed>
     */
    public function month(array $organizationIds, string $month): array
    {
        $today = ChinaCashRules::today();
        $this->chargeFixedCosts($today);
        $first = CarbonImmutable::parse($month.'-01');
        $end = $first->endOfMonth()->toDateString();
        $record = $this->record($month);
        $rate = $record && $record->exchange_rate !== null ? (float) $record->exchange_rate : null;
        $closed = $record && $record->closed_at !== null;
        $current = $month === $today->format('Y-m');

        $real = $this->movements->real($organizationIds, $first->toDateString(), $end);
        // Sin TC del mes los cobros y fletes (USD) no se pueden proyectar en RMB.
        $projected = $closed ? [] : array_values(array_filter(
            $this->movements->projected($organizationIds, $rate ?? 0.0, $today),
            fn (array $row) => str_starts_with($row['date'], $month)
        ));
        $all = array_merge($real, $projected);
        $closings = ChinaCashWeek::query()->where('month', $month)->get()->keyBy('starts_on');
        $weeks = [];
        $lastBalance = null;
        $opening = $this->opening($month);
        $calendar = ChinaCashRules::weeks($month);
        $usd = array_map(fn (array $row) => $row + ['from_week' => $this->weekOf($calendar, $row['confirmed_on'])], $this->movements->usdToExchange($organizationIds));
        foreach ($calendar as $week) {
            $closing = $closings->get($week['starts_on']);
            $lastBalance = $closing ? (float) $closing->balance : $lastBalance;
            $weeks[] = $week + [
                'totals' => ChinaCashRules::totals($all, $week['starts_on'], $week['ends_on']),
                'closing' => $closing ? ['balance' => (float) $closing->balance, 'usd_pending' => (float) $closing->usd_pending,
                    'screenshot_name' => $closing->screenshot_name, 'screenshot_url' => $this->files->url($closing->screenshot_path)] : null,
                'can_close' => !$closing && !$closed && $today->toDateString() >= $week['due_on'],
                'overdue' => !$closing && !$closed && $today->toDateString() > $week['due_on'],
                'usd_to_exchange' => $closing ? [] : array_values(array_filter($usd, fn (array $row) => $row['confirmed_on'] <= $week['ends_on'])),
                'system_balance' => $opening === null ? null : round($opening + ChinaCashRules::totals($real, null, min($week['ends_on'], $today->toDateString()))['profit'], 2),
            ];
        }
        $balance = $closed ? (float) $record->closing_balance : ($lastBalance ?? $opening);
        $totals = ChinaCashRules::totals($all);
        $pendingNet = $totals['income_projected'] - $totals['expense_projected'];

        return [
            'month' => $month, 'today' => $today->toDateString(), 'current' => $current, 'closed' => $closed,
            'exchange_rate' => $rate, 'opening_balance' => $opening, 'opening_editable' => $this->openingEditable($month) && !$closed,
            'balance' => $balance, 'projected_closing' => $opening === null ? null : round($opening + $totals['profit'] + $pendingNet, 2),
            'totals' => $totals, 'previous_totals' => $this->previousTotals($organizationIds, $first),
            'previous_weeks' => $this->previousWeeks($organizationIds, $first),
            'weeks' => $weeks,
            'real' => $real,
            'pending' => array_values(array_filter($projected, fn (array $row) => !$row['late'])),
            'late' => array_values(array_filter($projected, fn (array $row) => $row['late'])),
            'fixed_costs' => ChinaFixedCost::query()->orderBy('id')->get(['id', 'name', 'amount', 'active'])
                ->map(fn ($c) => ['id' => (int) $c->id, 'name' => $c->name, 'amount' => (float) $c->amount, 'active' => (bool) $c->active])->all(),
            'investments' => $this->investments((int) $first->year),
            'can_close_month' => $this->closeMonthBlocker($month, $today) === null,
            'close_month_blocker' => $this->closeMonthBlocker($month, $today),
        ];
    }

    /**
     * Cierres del año. El front los agrupa por mes, trimestre o semestre.
     *
     * @param  array<int, int>  $organizationIds
     * @return array<string, mixed>
     */
    public function history(array $organizationIds, int $year): array
    {
        $today = ChinaCashRules::today();
        $this->chargeFixedCosts($today);
        $current = $today->format('Y-m');
        $rows = [];
        $clients = ['in' => [], 'out' => []];
        $count = ['in' => 0, 'out' => 0];
        for ($m = 1; $m <= 12; $m++) {
            $month = sprintf('%04d-%02d', $year, $m);
            if ($month > $current) {
                break;
            }
            $record = $this->record($month);
            $closed = $record && $record->closed_at !== null;
            $first = CarbonImmutable::parse($month.'-01');
            $movements = $this->movements->real($organizationIds, $first->toDateString(), $first->endOfMonth()->toDateString());
            if ($month === $current && !$closed) {
                $movements = array_merge($movements, array_values(array_filter(
                    $this->movements->projected($organizationIds, (float) ($record->exchange_rate ?? 0), $today),
                    fn (array $row) => str_starts_with($row['date'], $month)
                )));
            }
            $opening = $this->opening($month);
            if (!$closed && $movements === [] && $opening === null) {
                continue;
            }
            $totals = ChinaCashRules::totals($movements);
            if ($closed) {
                $totals = ['income' => (float) $record->income_total, 'expense' => (float) $record->expense_total, 'income_projected' => 0.0, 'expense_projected' => 0.0];
            }
            foreach ($movements as $row) {
                $key = $row['client'] ?? '';
                $clients[$row['type']][$key] ??= ['client' => $row['client'], 'amount' => 0.0, 'movements' => 0];
                $clients[$row['type']][$key]['amount'] = round($clients[$row['type']][$key]['amount'] + $row['amount'], 2);
                $clients[$row['type']][$key]['movements']++;
                if ($month === $current) {
                    $count[$row['type']]++;
                }
            }
            $profit = round($totals['income'] + $totals['income_projected'] - $totals['expense'] - $totals['expense_projected'], 2);
            $rows[] = [
                'month' => $month, 'closed' => $closed, 'opening' => $opening ?? 0.0,
                'income' => $totals['income'], 'income_projected' => $totals['income_projected'],
                'expense' => $totals['expense'], 'expense_projected' => $totals['expense_projected'],
                'profit' => $profit, 'final' => round(($opening ?? 0.0) + $profit, 2),
                'bank' => $closed ? (float) $record->closing_balance : null,
            ];
        }

        return [
            'rows' => $rows,
            'clients' => array_map(fn (array $list) => array_values($list), $clients),
            'movements' => $count,
        ];
    }

    public function setRate(string $month, float $rate): void
    {
        $this->guardOpen($month);
        $this->save($month, ['exchange_rate' => $rate]);
    }

    /** TC con el que un flete en USD entra al flujo: el del mes del pago. */
    public function rateFor(string $date): float
    {
        $rate = $this->record(substr($date, 0, 7))->exchange_rate ?? null;
        if ($rate === null) {
            throw new ChinaBillingException('Registra primero el TC del mes en Flujo de caja.');
        }

        return (float) $rate;
    }

    public function guardOpen(string $month): void
    {
        $record = $this->record($month);
        if ($record && $record->closed_at !== null) {
            throw new ChinaBillingException('El mes ya está cerrado.', 409);
        }
    }

    public function setOpening(string $month, float $balance): void
    {
        $this->guardOpen($month);
        if (!$this->openingEditable($month)) {
            throw new ChinaBillingException('El saldo inicial ya no se edita: hay cierres registrados.', 409);
        }
        $this->save($month, ['opening_balance' => $balance]);
    }

    /** @param  array{id: int|null, organization_ids: array<int, int>}  $actor */
    public function closeWeek(string $month, string $startsOn, float $balance, UploadedFile $screenshot, array $actor): void
    {
        $this->guardOpen($month);
        $week = collect(ChinaCashRules::weeks($month))->firstWhere('starts_on', $startsOn);
        if (!$week) {
            throw new ChinaBillingException('Semana no válida.');
        }
        if (ChinaCashRules::today()->toDateString() < $week['due_on']) {
            throw new ChinaBillingException('Esta semana se cierra desde el '.CarbonImmutable::parse($week['due_on'])->format('d/m').'.', 409);
        }
        if (ChinaCashWeek::query()->where('month', $month)->where('starts_on', $startsOn)->exists()) {
            throw new ChinaBillingException('La semana ya está cerrada.', 409);
        }
        ChinaCashWeek::query()->create([
            'month' => $month, 'starts_on' => $week['starts_on'], 'ends_on' => $week['ends_on'], 'balance' => round($balance, 2),
            'usd_pending' => array_sum(array_column($this->movements->usdToExchange($actor['organization_ids'], $week['ends_on']), 'usd')),
            'screenshot_path' => $this->files->storeFile($screenshot, 'china-caja', 'cierre-'.$week['starts_on']),
            'screenshot_name' => $screenshot->getClientOriginalName(), 'closed_by' => $actor['id'],
        ]);
    }

    /** @param  array{id: int|null, organization_ids: array<int, int>}  $actor */
    public function closeMonth(string $month, float $balance, array $actor): void
    {
        $today = ChinaCashRules::today();
        $this->chargeFixedCosts($today);
        $blocker = $this->closeMonthBlocker($month, $today);
        if ($blocker !== null) {
            throw new ChinaBillingException($blocker, 409);
        }
        $first = CarbonImmutable::parse($month.'-01');
        $totals = ChinaCashRules::totals($this->movements->real($actor['organization_ids'], $first->toDateString(), $first->endOfMonth()->toDateString()));
        $this->save($month, [
            'closing_balance' => round($balance, 2), 'income_total' => $totals['income'], 'expense_total' => $totals['expense'],
            'closed_at' => now(), 'closed_by' => $actor['id'],
        ]);
    }

    /** @param  array{name: string, amount: float|string}  $data */
    public function createFixedCost(array $data): ChinaFixedCost
    {
        return ChinaFixedCost::query()->create($data + ['active' => true]);
    }

    public function toggleFixedCost(int $id, bool $active): void
    {
        $cost = $this->fixedCost($id);
        $cost->active = $active;
        $cost->save();
    }

    public function deleteFixedCost(int $id): void
    {
        $this->fixedCost($id)->delete();
    }

    /** Antes de cambiar un costo se descuenta lo que ya correspondía. */
    private function fixedCost(int $id): ChinaFixedCost
    {
        $this->chargeFixedCosts(ChinaCashRules::today());
        $cost = ChinaFixedCost::query()->find($id);
        if (!$cost) {
            throw new ChinaBillingException('Costo fijo no encontrado.', 404);
        }

        return $cost;
    }

    /** @param  array<string, mixed>  $data */
    public function createInvestment(array $data): ChinaInvestment
    {
        return ChinaInvestment::query()->create($data);
    }

    /**
     * Marcar pagada: el egreso va en su fecha programada (o hoy, si ese mes ya está cerrado).
     */
    public function payInvestment(int $id): void
    {
        DB::transaction(function () use ($id) {
            $row = ChinaInvestment::query()->lockForUpdate()->find($id);
            if (!$row || $row->paid_on !== null) {
                throw new ChinaBillingException('La inversión no existe o ya está pagada.', 409);
            }
            $record = $this->record(substr($row->due_on, 0, 7));
            $row->paid_on = $record && $record->closed_at !== null ? ChinaCashRules::today()->toDateString() : $row->due_on;
            $row->save();
        });
    }

    public function deleteInvestment(int $id): void
    {
        $row = ChinaInvestment::query()->find($id);
        if (!$row) {
            throw new ChinaBillingException('Inversión no encontrada.', 404);
        }
        if ($row->paid_on !== null) {
            $this->guardOpen(substr($row->paid_on, 0, 7));
        }
        $row->delete();
    }

    /**
     * Semanas vencidas o que tocan hoy, del mes actual y el anterior.
     *
     * @return list<array{month: string, starts_on: string, ends_on: string, due_on: string, days: int}>
     */
    public function weeksToClose(CarbonImmutable $today): array
    {
        // El flujo empieza en el primer mes con TC o saldo; si aún no hay, en el mes actual (lo mismo que muestra Flujo).
        $start = ChinaCashMonth::query()->min('month') ?? $today->format('Y-m');
        $out = [];
        foreach ([$today->startOfMonth()->subMonth()->format('Y-m'), $today->format('Y-m')] as $month) {
            $record = $this->record($month);
            if ($month < $start || ($record && $record->closed_at !== null)) {
                continue;
            }
            $closed = ChinaCashWeek::query()->where('month', $month)->pluck('starts_on')->all();
            foreach (ChinaCashRules::weeks($month) as $week) {
                if ($week['due_on'] <= $today->toDateString() && !in_array($week['starts_on'], $closed, true)) {
                    $out[] = ['month' => $month, 'starts_on' => $week['starts_on'], 'ends_on' => $week['ends_on'], 'due_on' => $week['due_on'],
                        'days' => (int) CarbonImmutable::parse($week['due_on'])->diffInDays($today)];
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @return list<array<string, mixed>>
     */
    public function late(array $organizationIds, CarbonImmutable $today): array
    {
        $rate = (float) ($this->record($today->format('Y-m'))->exchange_rate ?? 0);

        return array_values(array_filter($this->movements->projected($organizationIds, $rate, $today), fn (array $row) => $row['late']));
    }

    /** Descuenta los costos fijos activos de los meses que ya terminaron (o terminan hoy), una sola vez. */
    private function chargeFixedCosts(CarbonImmutable $today): void
    {
        foreach (ChinaFixedCost::query()->where('active', true)->get() as $cost) {
            $last = ChinaFixedCostCharge::query()->where('fixed_cost_id', $cost->id)->max('month');
            // Un costo pausado no se cobra hacia atrás al reactivarlo: se parte de la última edición.
            $from = max(CarbonImmutable::parse((string) $cost->updated_at)->format('Y-m'), $last ? CarbonImmutable::parse($last.'-01')->addMonth()->format('Y-m') : '');
            for ($month = CarbonImmutable::parse($from.'-01'); $month->endOfMonth()->toDateString() <= $today->toDateString(); $month = $month->addMonth()) {
                $record = $this->record($month->format('Y-m'));
                if ($record && $record->closed_at !== null) {
                    continue;
                }
                ChinaFixedCostCharge::query()->firstOrCreate(
                    ['month' => $month->format('Y-m'), 'fixed_cost_id' => $cost->id],
                    ['name' => $cost->name, 'amount' => $cost->amount, 'charged_on' => $month->endOfMonth()->toDateString()]
                );
            }
        }
    }

    private function closeMonthBlocker(string $month, CarbonImmutable $today): ?string
    {
        $record = $this->record($month);
        if ($record && $record->closed_at !== null) {
            return 'El mes ya está cerrado.';
        }
        $previousOpen = ChinaCashMonth::query()->where('month', '<', $month)->whereNull('closed_at')->exists();
        if ($previousOpen) {
            return 'Cierra primero el mes anterior.';
        }
        $closed = ChinaCashWeek::query()->where('month', $month)->pluck('starts_on')->all();
        $open = array_filter(ChinaCashRules::weeks($month), fn (array $week) => !in_array($week['starts_on'], $closed, true));
        if ($open === []) {
            return null;
        }
        $past = count(array_filter($open, fn (array $week) => $week['ends_on'] < $today->toDateString()));
        $running = count($open) - $past;

        return 'Primero cierra '.implode(' y ', array_filter([
            $past ? $past.($past === 1 ? ' semana sin cerrar' : ' semanas sin cerrar') : '',
            $running ? $running.($running === 1 ? ' semana aún en curso' : ' semanas aún en curso') : '',
        ]));
    }

    private function opening(string $month): ?float
    {
        $previous = ChinaCashMonth::query()->where('month', '<', $month)->whereNotNull('closed_at')->max('month');
        if ($previous === CarbonImmutable::parse($month.'-01')->subMonth()->format('Y-m')) {
            return (float) $this->record($previous)->closing_balance;
        }
        $own = $this->record($month)->opening_balance ?? null;

        return $own === null ? null : (float) $own;
    }

    /** Solo el primer mes y antes de cerrar una semana: después el saldo sale de los cierres. */
    private function openingEditable(string $month): bool
    {
        return !ChinaCashMonth::query()->where('month', '<', $month)->exists()
            && !ChinaCashWeek::query()->where('month', $month)->exists();
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @return array<string, float>
     */
    private function previousTotals(array $organizationIds, CarbonImmutable $first): array
    {
        $previous = $first->subMonth();

        return ChinaCashRules::totals($this->movements->real($organizationIds, $previous->toDateString(), $previous->endOfMonth()->toDateString()));
    }

    /** @return list<array<string, mixed>> */
    private function investments(int $year): array
    {
        return ChinaInvestment::query()->whereYear('due_on', $year)->orderBy('due_on')->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->name, 'amount' => (float) $row->amount, 'due_on' => $row->due_on, 'paid_on' => $row->paid_on])
            ->values()->all();
    }

    /**
     * Semana 1..n del mes en que se confirmó; null si viene de un mes anterior.
     *
     * @param  list<array{index: int, starts_on: string, ends_on: string, due_on: string}>  $weeks
     */
    private function weekOf(array $weeks, string $date): ?int
    {
        foreach ($weeks as $week) {
            if ($date >= $week['starts_on'] && $date <= $week['ends_on']) {
                return $week['index'] + 1;
            }
        }

        return null;
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @return list<array<string, float>>
     */
    private function previousWeeks(array $organizationIds, CarbonImmutable $first): array
    {
        $previous = $first->subMonth();
        $real = $this->movements->real($organizationIds, $previous->toDateString(), $previous->endOfMonth()->toDateString());

        return array_map(fn (array $week) => ChinaCashRules::totals($real, $week['starts_on'], $week['ends_on']), ChinaCashRules::weeks($previous->format('Y-m')));
    }

    private function record(string $month): ?ChinaCashMonth
    {
        return ChinaCashMonth::query()->where('month', $month)->first();
    }

    /** @param  array<string, mixed>  $values */
    private function save(string $month, array $values): void
    {
        ChinaCashMonth::query()->updateOrCreate(['month' => $month], $values);
    }
}
