<?php

namespace App\Services\ChinaCaja;

use App\Exceptions\ChinaBillingException;
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
        $closings = DB::table('china_cash_weeks')->where('month', $month)->get()->keyBy('starts_on');
        $weeks = [];
        $lastBalance = null;
        foreach (ChinaCashRules::weeks($month) as $week) {
            $closing = $closings->get($week['starts_on']);
            $lastBalance = $closing ? (float) $closing->balance : $lastBalance;
            $weeks[] = $week + [
                'totals' => ChinaCashRules::totals($all, $week['starts_on'], $week['ends_on']),
                'closing' => $closing ? ['balance' => (float) $closing->balance, 'usd_pending' => (float) $closing->usd_pending,
                    'screenshot_name' => $closing->screenshot_name, 'screenshot_url' => $this->files->url($closing->screenshot_path)] : null,
                'can_close' => !$closing && !$closed && $today->toDateString() >= $week['due_on'],
                'overdue' => !$closing && !$closed && $today->toDateString() > $week['due_on'],
                'usd_to_exchange' => $closing ? [] : $this->movements->usdToExchange($organizationIds, $week['ends_on']),
            ];
        }
        $opening = $this->opening($month);
        $balance = $closed ? (float) $record->closing_balance : ($lastBalance ?? $opening);
        $totals = ChinaCashRules::totals($all);
        $pendingNet = $totals['income_projected'] - $totals['expense_projected'];

        return [
            'month' => $month, 'today' => $today->toDateString(), 'current' => $current, 'closed' => $closed,
            'exchange_rate' => $rate, 'opening_balance' => $opening, 'opening_editable' => $this->openingEditable($month) && !$closed,
            'balance' => $balance, 'projected_closing' => $opening === null ? null : round($opening + $totals['profit'] + $pendingNet, 2),
            'totals' => $totals, 'previous_totals' => $this->previousTotals($organizationIds, $first),
            'weeks' => $weeks,
            'real' => $real,
            'pending' => array_values(array_filter($projected, fn (array $row) => !$row['late'])),
            'late' => array_values(array_filter($projected, fn (array $row) => $row['late'])),
            'fixed_costs' => DB::table('china_fixed_costs')->whereNull('deleted_at')->orderBy('id')->get(['id', 'name', 'amount', 'active'])
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
     * @return list<array<string, mixed>>
     */
    public function history(array $organizationIds, int $year): array
    {
        $today = ChinaCashRules::today();
        $this->chargeFixedCosts($today);
        $rows = [];
        for ($m = 1; $m <= 12; $m++) {
            $month = sprintf('%04d-%02d', $year, $m);
            if ($month > $today->format('Y-m')) {
                break;
            }
            $record = $this->record($month);
            $closed = $record && $record->closed_at !== null;
            $first = CarbonImmutable::parse($month.'-01');
            $totals = $closed
                ? ['income' => (float) $record->income_total, 'expense' => (float) $record->expense_total]
                : ChinaCashRules::totals($this->movements->real($organizationIds, $first->toDateString(), $first->endOfMonth()->toDateString()));
            $opening = $this->opening($month);
            if (!$closed && $totals['income'] == 0.0 && $totals['expense'] == 0.0 && $opening === null) {
                continue;
            }
            $calculated = $opening === null ? null : round($opening + $totals['income'] - $totals['expense'], 2);
            $bank = $closed ? (float) $record->closing_balance : null;
            $rows[] = [
                'month' => $month, 'closed' => $closed, 'opening' => $opening, 'income' => $totals['income'], 'expense' => $totals['expense'],
                'profit' => round($totals['income'] - $totals['expense'], 2), 'calculated' => $calculated, 'bank' => $bank,
                'difference' => $bank === null || $calculated === null ? null : round($bank - $calculated, 2),
            ];
        }

        return $rows;
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
            throw new ChinaBillingException('El saldo inicial viene del cierre del mes anterior.', 409);
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
        if (DB::table('china_cash_weeks')->where('month', $month)->where('starts_on', $startsOn)->exists()) {
            throw new ChinaBillingException('La semana ya está cerrada.', 409);
        }
        DB::table('china_cash_weeks')->insert([
            'month' => $month, 'starts_on' => $week['starts_on'], 'ends_on' => $week['ends_on'], 'balance' => round($balance, 2),
            'usd_pending' => array_sum(array_column($this->movements->usdToExchange($actor['organization_ids'], $week['ends_on']), 'usd')),
            'screenshot_path' => $this->files->storeFile($screenshot, 'china-caja', 'cierre-'.$week['starts_on']),
            'screenshot_name' => $screenshot->getClientOriginalName(), 'closed_by' => $actor['id'], 'created_at' => now(), 'updated_at' => now(),
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

    public function saveFixedCost(?int $id, array $data): void
    {
        $this->chargeFixedCosts(ChinaCashRules::today());
        if ($id === null) {
            DB::table('china_fixed_costs')->insert($data + ['active' => true, 'created_at' => now(), 'updated_at' => now()]);

            return;
        }
        $updated = DB::table('china_fixed_costs')->where('id', $id)->whereNull('deleted_at')->update($data + ['updated_at' => now()]);
        if (!$updated) {
            throw new ChinaBillingException('Costo fijo no encontrado.', 404);
        }
    }

    public function saveInvestment(array $data): void
    {
        DB::table('china_investments')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Marcar pagada; si se vuelve a invertir, crea la siguiente.
     *
     * @param  array{due_on: string, amount: float}|null  $again
     */
    public function payInvestment(int $id, string $paidOn, ?array $again): void
    {
        $this->guardOpen(substr($paidOn, 0, 7));
        DB::transaction(function () use ($id, $paidOn, $again) {
            $row = DB::table('china_investments')->where('id', $id)->lockForUpdate()->first();
            if (!$row || $row->paid_on !== null) {
                throw new ChinaBillingException('La inversión no existe o ya está pagada.', 409);
            }
            DB::table('china_investments')->where('id', $id)->update(['paid_on' => $paidOn, 'updated_at' => now()]);
            if ($again) {
                $this->saveInvestment(['name' => $row->name, 'previous_id' => $id] + $again);
            }
        });
    }

    public function deleteInvestment(int $id): void
    {
        if (!DB::table('china_investments')->where('id', $id)->whereNull('paid_on')->delete()) {
            throw new ChinaBillingException('Solo se eliminan inversiones sin pagar.', 409);
        }
    }

    /**
     * Semanas vencidas o que tocan hoy, del mes actual y el anterior.
     *
     * @return list<array{month: string, starts_on: string, ends_on: string, due_on: string, days: int}>
     */
    public function weeksToClose(CarbonImmutable $today): array
    {
        // El flujo empieza en el primer mes con TC o saldo: antes no hay semanas que reclamar.
        $start = DB::table('china_cash_months')->min('month');
        if (!$start) {
            return [];
        }
        $out = [];
        foreach ([$today->startOfMonth()->subMonth()->format('Y-m'), $today->format('Y-m')] as $month) {
            $record = $this->record($month);
            if ($month < $start || ($record && $record->closed_at !== null)) {
                continue;
            }
            $closed = DB::table('china_cash_weeks')->where('month', $month)->pluck('starts_on')->all();
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
        foreach (DB::table('china_fixed_costs')->whereNull('deleted_at')->where('active', true)->get() as $cost) {
            $last = DB::table('china_fixed_cost_charges')->where('fixed_cost_id', $cost->id)->max('month');
            // Un costo pausado no se cobra hacia atrás al reactivarlo: se parte de la última edición.
            $from = max(CarbonImmutable::parse((string) $cost->updated_at)->format('Y-m'), $last ? CarbonImmutable::parse($last.'-01')->addMonth()->format('Y-m') : '');
            for ($month = CarbonImmutable::parse($from.'-01'); $month->endOfMonth()->toDateString() <= $today->toDateString(); $month = $month->addMonth()) {
                $record = $this->record($month->format('Y-m'));
                if ($record && $record->closed_at !== null) {
                    continue;
                }
                DB::table('china_fixed_cost_charges')->insertOrIgnore([
                    'month' => $month->format('Y-m'), 'fixed_cost_id' => $cost->id, 'name' => $cost->name, 'amount' => $cost->amount,
                    'charged_on' => $month->endOfMonth()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    private function closeMonthBlocker(string $month, CarbonImmutable $today): ?string
    {
        $record = $this->record($month);
        if ($record && $record->closed_at !== null) {
            return 'El mes ya está cerrado.';
        }
        if ($today->toDateString() < CarbonImmutable::parse($month.'-01')->endOfMonth()->toDateString()) {
            return 'El mes se cierra desde su último día.';
        }
        $previousOpen = DB::table('china_cash_months')->where('month', '<', $month)->whereNull('closed_at')->exists();
        if ($previousOpen) {
            return 'Cierra primero el mes anterior.';
        }
        $closed = DB::table('china_cash_weeks')->where('month', $month)->count();

        return $closed < count(ChinaCashRules::weeks($month)) ? 'Cierra todas las semanas del mes.' : null;
    }

    private function opening(string $month): ?float
    {
        $previous = DB::table('china_cash_months')->where('month', '<', $month)->whereNotNull('closed_at')->max('month');
        if ($previous === CarbonImmutable::parse($month.'-01')->subMonth()->format('Y-m')) {
            return (float) $this->record($previous)->closing_balance;
        }
        $own = $this->record($month)->opening_balance ?? null;

        return $own === null ? null : (float) $own;
    }

    private function openingEditable(string $month): bool
    {
        return !DB::table('china_cash_months')->where('month', '<', $month)->exists();
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
        return DB::table('china_investments')->whereYear('due_on', $year)->orderBy('due_on')->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->name, 'amount' => (float) $row->amount, 'due_on' => $row->due_on, 'paid_on' => $row->paid_on])
            ->values()->all();
    }

    private function record(string $month): ?object
    {
        return DB::table('china_cash_months')->where('month', $month)->first();
    }

    /** @param  array<string, mixed>  $values */
    private function save(string $month, array $values): void
    {
        DB::table('china_cash_months')->insertOrIgnore(['month' => $month, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('china_cash_months')->where('month', $month)->update($values + ['updated_at' => now()]);
    }
}
