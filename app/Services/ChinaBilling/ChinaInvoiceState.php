<?php

namespace App\Services\ChinaBilling;

use App\Models\ChinaBilling\ChinaDocument;
use App\Models\ChinaBilling\ChinaOperation;
use Illuminate\Support\Carbon;

/**
 * Estado de la factura de cada operación: cuántas versiones hay, cuál es la vigente
 * y qué cambió desde que se emitió (para «Actualizar factura»).
 *
 * Todo sale con 3 consultas por página, sin importar cuántas filas tenga: conteos, vigentes y editores.
 * Los datos bancarios se comparan por huella contra la cuenta activa (leída una vez), así que
 * cambiar la cuenta no escribe en ninguna factura. Las operaciones en caja no se marcan: su factura está cerrada.
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

    public function __construct(private readonly ChinaBankAccountService $bankAccount)
    {
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  operaciones ya presentadas (ChinaBillingReader::presentOperation)
     * @return array<int, array<string, mixed>>  operation_id → estado de su factura
     */
    public function forRows(array $rows): array
    {
        $ids = array_values(array_filter(array_map(fn ($row) => empty($row['is_draft']) ? (int) ($row['id'] ?? 0) : 0, $rows)));
        if ($ids === []) {
            return [];
        }

        $invoices = ChinaDocument::query()->where('type', ChinaDocument::TYPE_INVOICE)->whereIn('operation_id', $ids);
        $counts = (clone $invoices)->selectRaw('operation_id, COUNT(*) as total, MAX(version) as last_version')
            ->groupBy('operation_id')->get()->keyBy('operation_id');
        $current = (clone $invoices)->where('status', ChinaDocument::STATUS_CURRENT)
            ->get(['id', 'operation_id', 'version', 'issued_on', 'snapshot'])->keyBy('operation_id');
        $editors = ChinaOperation::query()->whereIn('id', $ids)->get(['id', 'updated_at', 'updated_by_name'])->keyBy('id');
        $bank = ['fingerprint' => $this->bankAccount->activeFingerprint(), 'account' => $this->bankAccount->active()['account_number'] ?? ''] + $this->bankAccount->lastChange();

        $states = [];
        foreach ($rows as $row) {
            if (!empty($row['is_draft'])) {
                continue;
            }
            $id = (int) $row['id'];
            $states[$id] = $this->state($row, $counts->get($id), $current->get($id), $editors->get($id), $bank);
        }

        return $states;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{fingerprint: string, account: string, at: string|null, by: string|null}  $bank
     * @return array<string, mixed>
     */
    private function state(array $row, ?object $count, ?ChinaDocument $current, ?ChinaOperation $editor, array $bank): array
    {
        $locked = ($row['status'] ?? null) === ChinaBillingCalculator::STATUS_IN_CASH;
        $operationChanges = $current && !$locked ? self::changes((array) ($current->snapshot['operation'] ?? []), $row) : [];
        $bankChange = $current && !$locked ? self::bankChange((array) ($current->snapshot['bank'] ?? []), $bank) : null;
        $changes = $bankChange ? [...$operationChanges, $bankChange] : $operationChanges;

        // Fecha y autor del cambio más reciente (operación o cuenta bancaria).
        $sources = array_filter([
            $operationChanges !== [] ? ['at' => $editor?->updated_at?->toIso8601String(), 'by' => $editor?->updated_by_name] : null,
            $bankChange ? ['at' => $bank['at'], 'by' => $bank['by']] : null,
        ], fn ($source) => $source && $source['at']);
        usort($sources, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));

        return [
            'count' => (int) ($count->total ?? 0),
            'last_version' => (int) ($count->last_version ?? 0),
            'locked' => $locked,
            'current' => $current ? [
                'id' => (int) $current->id,
                'version' => (int) $current->version,
                'number' => $current->number(),
                'total' => $current->snapshot['operation']['total'] ?? null,
                'issued_on' => $current->issued_on?->toDateString(),
            ] : null,
            'changes' => $changes,
            'changed_at' => $sources[0]['at'] ?? null,
            'changed_by' => $sources[0]['by'] ?? null,
        ];
    }

    /**
     * Campos contables que difieren entre la operación facturada y la actual.
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

    /**
     * La cuenta bancaria impresa ya no es la activa. Si cambió el número se muestra (últimos 4); si no, solo que se actualizó.
     *
     * @param  array<string, mixed>  $invoicedBank
     * @param  array{fingerprint: string, account: string}  $active
     * @return array{field: string, label: string, from: string, to: string}|null
     */
    public static function bankChange(array $invoicedBank, array $active): ?array
    {
        if (ChinaBankAccountService::fingerprint($invoicedBank) === $active['fingerprint']) {
            return null;
        }
        $before = trim((string) ($invoicedBank['account_number'] ?? ''));
        $after = trim($active['account']);
        $tail = fn (string $account) => $account === '' ? '—' : '…'.mb_substr($account, -4);

        return $before !== $after
            ? ['field' => 'bank', 'label' => 'Cuenta bancaria', 'from' => $tail($before), 'to' => $tail($after)]
            : ['field' => 'bank', 'label' => 'Datos bancarios', 'from' => 'Anteriores', 'to' => 'Actualizados'];
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
            'date' => Carbon::parse((string) $value)->format('d/m/y'),
            default => (string) $value,
        };
    }
}
