<?php

namespace App\Services\ChinaCaja;

use Carbon\CarbonImmutable;

/**
 * Reglas de Etapas (Carga consolidada) y de Estado de contenedores. Sin base de datos.
 */
class ChinaContainerRules
{
    public const CBM_TO_UNLOCK = 60.0;

    public const BL_DAYS_AFTER_SAILING = 21;

    /** Telex, flete y cobro: una semana antes del arribo. */
    public const DAYS_BEFORE_ARRIVAL = 7;

    public const CONTAINER_TYPES = ['LCL', '20 ST', '40 NOR', '40 ST', '40 HQ'];

    /** @var array<string, string> */
    public const RESERVATION = [
        'container_type' => 'Tipo de contenedor', 'shipper' => 'Shipping', 'freight_usd' => 'Precio flete',
        'filling_on' => 'Fecha de llenado', 'sailing_on' => 'Fecha de zarpe', 'arrival_on' => 'Fecha de llegada',
        'shipping_line' => 'Línea', 'destination_port' => 'Puerto destino', 'free_days' => 'Sobrestadía',
    ];

    /**
     * @param  array<string, mixed>  $data  datos de la reserva; `own_reservation` = China ya escribió alguno (los heredados de Documentación no desbloquean)
     * @return array<string, mixed>
     */
    public static function progress(array $data, float $cbm, bool $closed, bool $hasOperation, CarbonImmutable $today): array
    {
        $has = fn (string $key): bool => trim((string) ($data[$key] ?? '')) !== '' && ($key !== 'freight_usd' || (float) $data[$key] > 0);
        $missing = array_values(array_filter(self::RESERVATION, fn (string $key) => !$has($key), ARRAY_FILTER_USE_KEY));
        $firstLocked = $cbm < self::CBM_TO_UNLOCK && empty($data['own_reservation']);
        $first = $firstLocked ? 0 : count(self::RESERVATION) - count($missing);
        $second = count(array_filter([!empty($data['list_updated']), $has('bl_consignee'), $has('bl_description')]));
        $third = count(array_filter([$hasOperation, $has('bl_draft_path'), $has('bl_number')]));

        $activates = $has('filling_on') ? CarbonImmutable::parse((string) $data['filling_on'])->subDays(2) : null;
        $stages = [
            ['number' => 1, 'title' => 'Reserva de contenedor', 'done' => $first, 'total' => count(self::RESERVATION),
                'locked' => $firstLocked, 'lock_message' => 'Bloqueada hasta 60 CBM'],
            ['number' => 2, 'title' => 'Días antes del llenado', 'done' => $second, 'total' => 3,
                'locked' => $second === 0 && ($activates === null || $today->lt($activates)),
                'lock_message' => $activates
                    ? 'Se activa el '.$activates->format('d/m').' (2 días antes del llenado)'
                    : 'Se activa 2 días antes de la fecha de llenado'],
            ['number' => 3, 'title' => 'Después del llenado', 'done' => $third, 'total' => 3,
                'locked' => $third === 0 && !$closed, 'lock_message' => 'Se activa cuando China cierre el consolidado'],
        ];
        $done = $first + $second + $third;
        $total = array_sum(array_column($stages, 'total'));
        $current = null;
        foreach ($stages as $stage) {
            if (!$stage['locked'] && $stage['done'] < $stage['total']) {
                $current = $stage['number'];
                break;
            }
        }

        return [
            'stages' => $stages, 'percent' => (int) round($done / $total * 100), 'current' => $current,
            'complete' => $done === $total, 'missing_reservation' => $firstLocked ? array_values(self::RESERVATION) : $missing,
        ];
    }

    /**
     * Estado del contenedor y qué se puede pedir ya.
     *
     * @param  array{sailing_on: ?string, arrival_on: ?string, has_bl: bool, telex: bool, collected: bool, freight_paid: bool}  $row
     * @return array{group: string, completed: bool, days_to_arrival: ?int, bl_due: bool, arrival_due: bool, bl_from: ?string, arrival_from: ?string}
     */
    public static function status(array $row, CarbonImmutable $today): array
    {
        $sailing = $row['sailing_on'] ? CarbonImmutable::parse($row['sailing_on']) : null;
        $arrival = $row['arrival_on'] ? CarbonImmutable::parse($row['arrival_on']) : null;
        $days = $arrival ? (int) $today->diffInDays($arrival, false) : null;
        $completed = $row['has_bl'] && $row['telex'] && $row['collected'] && $row['freight_paid'];
        $group = match (true) {
            $completed => 'completed',
            $sailing !== null && $sailing->gt($today) => 'to_sail',
            $days === null => 'no_date',
            $days < 0 => 'arrived',
            $days <= self::DAYS_BEFORE_ARRIVAL => 'this_week',
            default => 'in_transit',
        };

        return [
            'group' => $group, 'completed' => $completed, 'days_to_arrival' => $days,
            'bl_due' => $sailing !== null && $today->gte($sailing->addDays(self::BL_DAYS_AFTER_SAILING)),
            'arrival_due' => $days !== null && $days <= self::DAYS_BEFORE_ARRIVAL,
            'bl_from' => $sailing?->addDays(self::BL_DAYS_AFTER_SAILING)->toDateString(),
            'arrival_from' => $arrival?->subDays(self::DAYS_BEFORE_ARRIVAL)->toDateString(),
        ];
    }
}
