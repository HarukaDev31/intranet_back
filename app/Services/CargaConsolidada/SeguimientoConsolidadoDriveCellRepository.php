<?php

namespace App\Services\CargaConsolidada;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeguimientoConsolidadoDriveCellRepository
{
    /**
     * @return array<string, string> row_key => cell_value
     */
    public function manualValuesByColumn(int $idContenedor, string $sheetName, string $columnKey): array
    {
        if (!Schema::hasTable('contenedor_seguimiento_drive_cells')) {
            return [];
        }

        return DB::table('contenedor_seguimiento_drive_cells')
            ->where('id_contenedor', $idContenedor)
            ->where('sheet_name', $sheetName)
            ->where('column_key', $columnKey)
            ->where('is_manual', true)
            ->pluck('cell_value', 'row_key')
            ->map(function ($value) {
                return trim((string) $value);
            })
            ->filter(function ($value) {
                return $value !== '';
            })
            ->all();
    }

    /**
     * @return array<int, object>
     */
    public function manualCellsForSheet(int $idContenedor, string $sheetName): array
    {
        if (!Schema::hasTable('contenedor_seguimiento_drive_cells')) {
            return [];
        }

        return DB::table('contenedor_seguimiento_drive_cells')
            ->where('id_contenedor', $idContenedor)
            ->where('sheet_name', $sheetName)
            ->where('is_manual', true)
            ->whereNotNull('column_letter')
            ->orderBy('row_key')
            ->orderBy('column_letter')
            ->get()
            ->all();
    }

    /**
     * @param array{
     *   id_contenedor:int,
     *   sheet_name:string,
     *   row_key:string,
     *   column_key:string,
     *   id_cotizacion?:int|null,
     *   id_proveedor?:int|null,
     *   cell_ref?:string|null,
     *   row_number?:int|null,
     *   column_letter?:string|null,
     *   cell_value?:string|null,
     *   is_manual:bool,
     *   change_source:string,
     *   snapshot_id?:int|null
     * } $payload
     * @return array{changed:bool, cell_id:int|null}
     */
    public function upsertCell(array $payload): array
    {
        if (!Schema::hasTable('contenedor_seguimiento_drive_cells')) {
            return ['changed' => false, 'cell_id' => null];
        }

        $now = Carbon::now();
        $newValue = $this->normalizeValue($payload['cell_value'] ?? null);

        $existing = DB::table('contenedor_seguimiento_drive_cells')
            ->where('id_contenedor', (int) $payload['id_contenedor'])
            ->where('sheet_name', (string) $payload['sheet_name'])
            ->where('row_key', (string) $payload['row_key'])
            ->where('column_key', (string) $payload['column_key'])
            ->first();

        $oldValue = $existing ? $this->normalizeValue($existing->cell_value) : null;
        $changed = $oldValue !== $newValue;

        $row = [
            'id_cotizacion' => $payload['id_cotizacion'] ?? null,
            'id_proveedor' => $payload['id_proveedor'] ?? null,
            'cell_ref' => $payload['cell_ref'] ?? null,
            'row_number' => $payload['row_number'] ?? null,
            'column_letter' => $payload['column_letter'] ?? null,
            'cell_value' => $newValue,
            'is_manual' => (bool) ($payload['is_manual'] ?? false),
            'last_seen_at' => $now,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('contenedor_seguimiento_drive_cells')
                ->where('id', $existing->id)
                ->update($row);
            $cellId = (int) $existing->id;
        } else {
            $cellId = (int) DB::table('contenedor_seguimiento_drive_cells')->insertGetId(array_merge($row, [
                'id_contenedor' => (int) $payload['id_contenedor'],
                'sheet_name' => (string) $payload['sheet_name'],
                'row_key' => (string) $payload['row_key'],
                'column_key' => (string) $payload['column_key'],
                'created_at' => $now,
            ]));
        }

        if ($changed && Schema::hasTable('contenedor_seguimiento_drive_cell_history')) {
            DB::table('contenedor_seguimiento_drive_cell_history')->insert([
                'cell_id' => $cellId,
                'snapshot_id' => $payload['snapshot_id'] ?? null,
                'id_contenedor' => (int) $payload['id_contenedor'],
                'sheet_name' => (string) $payload['sheet_name'],
                'row_key' => (string) $payload['row_key'],
                'column_key' => (string) $payload['column_key'],
                'cell_ref' => $payload['cell_ref'] ?? null,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'change_source' => (string) ($payload['change_source'] ?? 'drive_pull'),
                'created_at' => $now,
            ]);
        }

        return ['changed' => $changed, 'cell_id' => $cellId];
    }

    public function createSnapshot(int $idContenedor, ?string $driveFileId, ?string $fileName, string $trigger): int
    {
        if (!Schema::hasTable('contenedor_seguimiento_drive_snapshots')) {
            return 0;
        }

        return (int) DB::table('contenedor_seguimiento_drive_snapshots')->insertGetId([
            'id_contenedor' => $idContenedor,
            'drive_file_id' => $driveFileId,
            'file_name' => $fileName,
            'trigger' => $trigger,
            'status' => 'running',
            'created_at' => Carbon::now(),
        ]);
    }

    public function finishSnapshot(int $snapshotId, int $cellsUpserted, int $cellsHistory, string $status = 'ok', ?string $error = null): void
    {
        if ($snapshotId <= 0 || !Schema::hasTable('contenedor_seguimiento_drive_snapshots')) {
            return;
        }

        DB::table('contenedor_seguimiento_drive_snapshots')
            ->where('id', $snapshotId)
            ->update([
                'cells_upserted' => $cellsUpserted,
                'cells_history' => $cellsHistory,
                'status' => $status,
                'error' => $error,
            ]);
    }

    private function normalizeValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
