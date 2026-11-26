<?php

namespace App\Services\ChinaBilling;

use App\Models\ChinaBilling\ChinaDocument;
use App\Models\ChinaBilling\ChinaOperation;

/**
 * Estado de la factura de cada operación: cuántas versiones hay, cuál es la vigente
 * y qué datos de la operación cambiaron desde que se emitió (para «Actualizar factura»).
 */
class ChinaInvoiceState
{
    /** Campos contables que la factura imprime: clave del detalle → etiqueta y formato. */
    private const FIELDS = [
        'issued_on' => ['Fecha', 'date'],
        'organization.name' => ['Cliente', 'text'],
        'service_name' => ['Servicio', 'text'],
        'variant_name' => ['Variante', 'text'],
        'cbm' => ['Cantidad de CBM', 'number'],
        'rate' => ['Tarifa por CBM', 'money'],
        'service_amount' => ['Monto del servicio', 'money'],
        'freight' => ['Flete marítimo', 'money'],
        'fob' => ['Gasto FOB', 'money'],
        'extras_amount' => ['Otros costos', 'money'],
        'total' => ['Importe total', 'money'],
    ];

    /**
     * @param  array<int, array<string, mixed>>  $rows  operaciones ya presentadas (ChinaBillingReader::presentOperation)
     * @return array<int, array<string, mixed>>  operation_id → estado de su factura
     */
    public static function forRows(array $rows): array
    {
        $ids = array_values(array_filter(array_map(fn ($row) => empty($row['is_draft']) ? (int) ($row['id'] ?? 0) : 0, $rows)));
        if ($ids === []) {
            return [];
        }

        $counts = ChinaDocument::query()->where('type', ChinaDocument::TYPE_INVOICE)->whereIn('operation_id', $ids)
            ->selectRaw('operation_id, COUNT(*) as total, MAX(version) as last_version')
            ->groupBy('operation_id')->get()->keyBy('operation_id');
        $current = ChinaDocument::query()->where('type', ChinaDocument::TYPE_INVOICE)->whereIn('operation_id', $ids)
            ->where('status', ChinaDocument::STATUS_CURRENT)->get()->keyBy('operation_id');
        $editors = ChinaOperation::query()->whereIn('id', $ids)->get(['id', 'updated_at', 'updated_by_name'])->keyBy('id');

        $states = [];
        foreach ($rows as $row) {
            if (!empty($row['is_draft'])) {
                continue;
            }
            $id = (int) $row['id'];
            $states[$id] = self::state($row, $counts->get($id), $current->get($id), $editors->get($id));
        }

        return $states;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function state(array $row, ?object $count, ?ChinaDocument $current, ?ChinaOperation $editor): array
    {
        $changes = $current ? self::changes((array) ($current->snapshot['operation'] ?? []), $row) : [];

        return [
            'count' => (int) ($count->total ?? 0),
            'last_version' => (int) ($count->last_version ?? 0),
            'locked' => ($row['status'] ?? null) === ChinaBillingCalculator::STATUS_IN_CASH,
            'current' => $current ? [
                'id' => (int) $current->id,
                'version' => (int) $current->version,
                'number' => $current->number(),
                'total' => $current->snapshot['operation']['total'] ?? null,
                'issued_on' => $current->issued_on?->toDateString(),
            ] : null,
            'changes' => $changes,
            'changed_at' => $changes !== [] ? $editor?->updated_at?->toIso8601String() : null,
            'changed_by' => $changes !== [] ? $editor?->updated_by_name : null,
        ];
    }

    /**
     * Campos que difieren entre la operación facturada y la actual.
     *
     * @param  array<string, mixed>  $invoiced
     * @param  array<string, mixed>  $now
     * @return array<int, array{field: string, label: string, from: string, to: string}>
     */
    public static function changes(array $invoiced, array $now): array
    {
        $changes = [];
        foreach (self::FIELDS as $path => [$label, $format]) {
            $before = data_get($invoiced, $path);
            $after = data_get($now, $path);
            if (self::same($before, $after, $format)) {
                continue;
            }
            $changes[] = ['field' => $path, 'label' => $label, 'from' => self::format($before, $format), 'to' => self::format($after, $format)];
        }

        return $changes;
    }

    private static function same(mixed $a, mixed $b, string $format): bool
    {
        if (in_array($format, ['money', 'number'], true)) {
            return abs((float) $a - (float) $b) < 0.005;
        }

        return trim((string) $a) === trim((string) $b);
    }

    private static function format(mixed $value, string $format): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($format) {
            'money' => '$'.number_format((float) $value, 2, '.', ','),
            'number' => number_format((float) $value, 2, '.', ','),
            'date' => \Illuminate\Support\Carbon::parse((string) $value)->format('d/m/y'),
            default => (string) $value,
        };
    }
}
