<?php

namespace App\Services\ChinaCaja;

use Carbon\CarbonImmutable;

/**
 * Calendario y totales del Flujo de caja (RMB). Sin base de datos.
 */
class ChinaCashRules
{
    /** El negocio de caja se cuenta en hora de China. */
    public const TIMEZONE = 'Asia/Shanghai';

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now(self::TIMEZONE)->toDateString());
    }

    /**
     * Semanas de lunes a domingo; una punta de menos de 3 días se une a la semana vecina.
     *
     * @return list<array{index: int, starts_on: string, ends_on: string, due_on: string}>
     */
    public static function weeks(string $month): array
    {
        $first = CarbonImmutable::parse($month.'-01');
        $last = $first->daysInMonth;
        $ranges = [];
        $start = 1;
        for ($day = 1; $day <= $last; $day++) {
            if ($first->addDays($day - 1)->isSunday() || $day === $last) {
                $ranges[] = [$start, $day];
                $start = $day + 1;
            }
        }
        if (count($ranges) > 1 && $ranges[0][1] - $ranges[0][0] < 2) {
            $ranges[1][0] = 1;
            array_shift($ranges);
        }
        $end = count($ranges) - 1;
        if ($end > 0 && $ranges[$end][1] - $ranges[$end][0] < 2) {
            $ranges[$end - 1][1] = $ranges[$end][1];
            array_pop($ranges);
        }

        $weeks = [];
        foreach ($ranges as $index => [$from, $to]) {
            $weeks[] = [
                'index' => $index,
                'starts_on' => $first->addDays($from - 1)->toDateString(),
                'ends_on' => $first->addDays($to - 1)->toDateString(),
                // El lunes siguiente; la última semana se cierra el último día del mes.
                'due_on' => $first->addDays($to === $last ? $to - 1 : $to)->toDateString(),
            ];
        }

        return $weeks;
    }

    public static function dueBeforeArrival(string $arrival): CarbonImmutable
    {
        return CarbonImmutable::parse($arrival)->subDays(ChinaContainerRules::DAYS_BEFORE_ARRIVAL);
    }

    /**
     * @param  list<array<string, mixed>>  $movements  con date, type (in|out), amount y real
     * @return array{income: float, income_projected: float, expense: float, expense_projected: float, profit: float, profit_projected: float}
     */
    public static function totals(array $movements, ?string $from = null, ?string $to = null): array
    {
        $sum = ['in1' => 0.0, 'in0' => 0.0, 'out1' => 0.0, 'out0' => 0.0];
        foreach ($movements as $movement) {
            if (($from !== null && $movement['date'] < $from) || ($to !== null && $movement['date'] > $to)) {
                continue;
            }
            $sum[$movement['type'].($movement['real'] ? '1' : '0')] += (float) $movement['amount'];
        }

        return [
            'income' => round($sum['in1'], 2), 'income_projected' => round($sum['in0'], 2),
            'expense' => round($sum['out1'], 2), 'expense_projected' => round($sum['out0'], 2),
            'profit' => round($sum['in1'] - $sum['out1'], 2),
            'profit_projected' => round($sum['in1'] + $sum['in0'] - $sum['out1'] - $sum['out0'], 2),
        ];
    }
}
