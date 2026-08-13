<?php

namespace App\Services\CargaConsolidada;

use App\Models\CargaConsolidada\Contenedor;
use App\Services\Google\GoogleDriveSeguimientoConsolidadoService;
use App\Support\CargaConsolidada\SeguimientoDriveCellRowKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SeguimientoConsolidadoDriveCellSyncService
{
    private const LOG_PREFIX = '[SeguimientoDriveCells]';

    /** @var GoogleDriveSeguimientoConsolidadoService */
    private $driveService;

    /** @var SeguimientoConsolidadoDriveCellRepository */
    private $repository;

    public function __construct(
        GoogleDriveSeguimientoConsolidadoService $driveService,
        SeguimientoConsolidadoDriveCellRepository $repository
    ) {
        $this->driveService = $driveService;
        $this->repository = $repository;
    }

    /**
     * Lee el Excel actual en Drive y actualiza celdas + historial en BD.
     *
     * @return array{success:bool, message?:string, cells_upserted?:int, cells_history?:int}
     */
    public function pullFromDrive(int $idContenedor, string $trigger = 'command'): array
    {
        $contenedor = Contenedor::find($idContenedor);
        if (!$contenedor) {
            return ['success' => false, 'message' => 'Consolidado no encontrado'];
        }

        $fileId = trim((string) ($contenedor->excel_seguimiento_drive_file_id ?? ''));
        if ($fileId === '') {
            return ['success' => false, 'message' => 'Consolidado sin excel_seguimiento_drive_file_id'];
        }

        if (!$this->driveService->isConfigured()) {
            return ['success' => false, 'message' => 'Google Drive no configurado'];
        }

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('seg_drive_pull_') . '.xlsx';
        $snapshotId = $this->repository->createSnapshot(
            $idContenedor,
            $fileId,
            $contenedor->excel_seguimiento_file_name,
            $trigger
        );

        try {
            if (!$this->driveService->downloadFileByIdToPath($fileId, $tmpPath)) {
                throw new \RuntimeException('No se pudo descargar el Excel desde Drive');
            }

            $spreadsheet = IOFactory::load($tmpPath);
            $cellsUpserted = 0;
            $cellsHistory = 0;

            $cotizacionesSheet = $spreadsheet->getSheetByName('Cotizaciones');
            if ($cotizacionesSheet !== null) {
                [$upserted, $history] = $this->syncCotizacionesSheet(
                    $cotizacionesSheet,
                    $idContenedor,
                    $snapshotId,
                    $trigger
                );
                $cellsUpserted += $upserted;
                $cellsHistory += $history;
            }

            $seguimientoSheet = $spreadsheet->getSheetByName('Seguimiento');
            if ($seguimientoSheet !== null) {
                [$upserted, $history] = $this->syncSeguimientoYiwuNotes(
                    $seguimientoSheet,
                    $idContenedor,
                    $snapshotId,
                    $trigger
                );
                $cellsUpserted += $upserted;
                $cellsHistory += $history;

                [$upserted, $history] = $this->syncSeguimientoContactarNotes(
                    $seguimientoSheet,
                    $idContenedor,
                    $snapshotId,
                    $trigger
                );
                $cellsUpserted += $upserted;
                $cellsHistory += $history;

                [$upserted, $history] = $this->syncSeguimientoUrgenciaNotes(
                    $seguimientoSheet,
                    $idContenedor,
                    $snapshotId,
                    $trigger
                );
                $cellsUpserted += $upserted;
                $cellsHistory += $history;
            }

            $this->repository->finishSnapshot($snapshotId, $cellsUpserted, $cellsHistory, 'ok');

            Log::info(self::LOG_PREFIX . ' Pull completado', [
                'id_contenedor' => $idContenedor,
                'trigger' => $trigger,
                'cells_upserted' => $cellsUpserted,
                'cells_history' => $cellsHistory,
            ]);

            return [
                'success' => true,
                'cells_upserted' => $cellsUpserted,
                'cells_history' => $cellsHistory,
            ];
        } catch (\Throwable $e) {
            $this->repository->finishSnapshot($snapshotId, 0, 0, 'failed', $e->getMessage());

            Log::error(self::LOG_PREFIX . ' Pull fallido', [
                'id_contenedor' => $idContenedor,
                'trigger' => $trigger,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            // Asegura alerta Slack aunque LOG_STACK_CHANNELS no incluya el canal slack.
            try {
                Log::channel('slack')->error(self::LOG_PREFIX . ' Pull fallido', [
                    'id_contenedor' => $idContenedor,
                    'trigger' => $trigger,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ]);
            } catch (\Throwable $slackError) {
                Log::warning(self::LOG_PREFIX . ' No se pudo notificar a Slack', [
                    'error' => $slackError->getMessage(),
                ]);
            }

            return ['success' => false, 'message' => $e->getMessage()];
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    public function applyManualCellsToLocalFile(int $idContenedor, string $localPath): void
    {
        if (!is_file($localPath)) {
            return;
        }

        $spreadsheet = IOFactory::load($localPath);
        $changed = false;

        $cotizacionesSheet = $spreadsheet->getSheetByName('Cotizaciones');
        if ($cotizacionesSheet !== null) {
            $changed = $this->applyManualCotizacionesCells($cotizacionesSheet, $idContenedor) || $changed;
        }

        $seguimientoSheet = $spreadsheet->getSheetByName('Seguimiento');
        if ($seguimientoSheet !== null) {
            $changed = $this->applyManualSeguimientoNotes($seguimientoSheet, $idContenedor) || $changed;
        }

        if (!$changed) {
            return;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($localPath);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function syncCotizacionesSheet(
        Worksheet $sheet,
        int $idContenedor,
        int $snapshotId,
        string $trigger
    ): array {
        $config = (array) config('seguimiento_drive_cells.sheets.Cotizaciones', []);
        $columns = (array) ($config['columns'] ?? []);
        $startRow = (int) ($config['data_start_row'] ?? 2);
        $highestRow = (int) $sheet->getHighestDataRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $preserveExtraColumns = (bool) ($config['preserve_extra_columns'] ?? true);
        $columnDefinitions = $this->buildCotizacionesColumnDefinitions($columns, $highestColumnIndex, $preserveExtraColumns);

        $upserted = 0;
        $history = 0;

        foreach ($columnDefinitions as $definition) {
            if (empty($definition['is_manual']) || (int) $definition['index'] <= 0) {
                continue;
            }

            $letter = (string) $definition['letter'];
            $value = $sheet->getCell($letter . '1')->getCalculatedValue();
            $result = $this->repository->upsertCell([
                'id_contenedor' => $idContenedor,
                'sheet_name' => 'Cotizaciones',
                'row_key' => '__header__',
                'column_key' => (string) $definition['column_key'],
                'id_cotizacion' => null,
                'id_proveedor' => null,
                'cell_ref' => $letter . '1',
                'row_number' => 1,
                'column_letter' => $letter,
                'cell_value' => $value,
                'is_manual' => true,
                'change_source' => $trigger,
                'snapshot_id' => $snapshotId,
            ]);

            $upserted++;
            if ($result['changed']) {
                $history++;
            }
        }

        for ($row = $startRow; $row <= $highestRow; $row++) {
            $nombre = trim((string) $sheet->getCell('D' . $row)->getCalculatedValue());
            $whatsapp = trim((string) $sheet->getCell('E' . $row)->getCalculatedValue());
            $codeSupplier = trim((string) $sheet->getCell('F' . $row)->getCalculatedValue());

            if ($nombre === '' && $whatsapp === '' && $codeSupplier === '') {
                continue;
            }

            $resolved = $this->resolveCotizacionesRow($idContenedor, $nombre, $whatsapp, $codeSupplier);
            if ($resolved === null) {
                continue;
            }

            foreach ($columnDefinitions as $definition) {
                $letter = (string) ($definition['letter'] ?? '');
                if ($letter === '') {
                    continue;
                }

                $cellRef = $letter . $row;
                $value = $sheet->getCell($cellRef)->getCalculatedValue();
                $result = $this->repository->upsertCell([
                    'id_contenedor' => $idContenedor,
                    'sheet_name' => 'Cotizaciones',
                    'row_key' => $resolved['row_key'],
                    'column_key' => (string) $definition['column_key'],
                    'id_cotizacion' => $resolved['id_cotizacion'],
                    'id_proveedor' => $resolved['id_proveedor'],
                    'cell_ref' => $cellRef,
                    'row_number' => $row,
                    'column_letter' => $letter,
                    'cell_value' => $value,
                    'is_manual' => (bool) ($definition['is_manual'] ?? false),
                    'change_source' => $trigger,
                    'snapshot_id' => $snapshotId,
                ]);

                $upserted++;
                if ($result['changed']) {
                    $history++;
                }
            }
        }

        return [$upserted, $history];
    }

    /**
     * @param array<string, array<string, mixed>> $configuredColumns
     * @return array<int, array{index:int,letter:string,column_key:string,is_manual:bool}>
     */
    private function buildCotizacionesColumnDefinitions(array $configuredColumns, int $highestColumnIndex, bool $preserveExtraColumns): array
    {
        $definitions = [];
        $configuredByLetter = [];
        $maxConfiguredIndex = 0;
        $minConfiguredIndex = PHP_INT_MAX;

        foreach ($configuredColumns as $columnKey => $columnConfig) {
            $letter = strtoupper((string) ($columnConfig['letter'] ?? ''));
            if ($letter === '') {
                continue;
            }

            $index = Coordinate::columnIndexFromString($letter);
            $maxConfiguredIndex = max($maxConfiguredIndex, $index);
            $minConfiguredIndex = min($minConfiguredIndex, $index);
            $configuredByLetter[$letter] = [
                'index' => $index,
                'letter' => $letter,
                'column_key' => (string) $columnKey,
                'is_manual' => (bool) ($columnConfig['is_manual'] ?? false),
            ];
        }

        $lastIndex = $preserveExtraColumns ? max($highestColumnIndex, $maxConfiguredIndex) : $maxConfiguredIndex;
        $firstIndex = $minConfiguredIndex === PHP_INT_MAX ? 1 : $minConfiguredIndex;
        for ($index = $firstIndex; $index <= $lastIndex; $index++) {
            $letter = Coordinate::stringFromColumnIndex($index);
            if (isset($configuredByLetter[$letter])) {
                $definitions[] = $configuredByLetter[$letter];
                continue;
            }

            // Cualquier columna no generada por el sistema se trata como manual.
            $definitions[] = [
                'index' => $index,
                'letter' => $letter,
                'column_key' => 'col_' . $letter,
                'is_manual' => true,
            ];
        }

        return $definitions;
    }

    private function applyManualCotizacionesCells(Worksheet $sheet, int $idContenedor): bool
    {
        $manualCells = $this->repository->manualCellsForSheet($idContenedor, 'Cotizaciones');
        if ($manualCells === []) {
            return false;
        }

        $rowMap = $this->buildCotizacionesRowMap($sheet, $idContenedor);
        $changed = false;

        foreach ($manualCells as $cell) {
            $value = $cell->cell_value;
            if ($value === null) {
                continue;
            }

            $rowKey = (string) $cell->row_key;
            $rowNumber = $rowKey === '__header__'
                ? 1
                : ($rowMap[$rowKey] ?? null);

            if ($rowNumber === null) {
                continue;
            }

            $columnLetter = strtoupper((string) $cell->column_letter);
            if ($columnLetter === '') {
                continue;
            }

            $cellRef = $columnLetter . $rowNumber;
            if ((string) $sheet->getCell($cellRef)->getValue() === (string) $value) {
                continue;
            }

            $sheet->setCellValue($cellRef, $value);
            $changed = true;
        }

        return $changed;
    }

    /**
     * @return array<string, int>
     */
    private function buildCotizacionesRowMap(Worksheet $sheet, int $idContenedor): array
    {
        $config = (array) config('seguimiento_drive_cells.sheets.Cotizaciones', []);
        $startRow = (int) ($config['data_start_row'] ?? 2);
        $highestRow = (int) $sheet->getHighestDataRow();
        $map = [];

        for ($row = $startRow; $row <= $highestRow; $row++) {
            $nombre = trim((string) $sheet->getCell('D' . $row)->getCalculatedValue());
            $whatsapp = trim((string) $sheet->getCell('E' . $row)->getCalculatedValue());
            $codeSupplier = trim((string) $sheet->getCell('F' . $row)->getCalculatedValue());

            if ($nombre === '' && $whatsapp === '' && $codeSupplier === '') {
                continue;
            }

            $resolved = $this->resolveCotizacionesRow($idContenedor, $nombre, $whatsapp, $codeSupplier);
            if ($resolved !== null) {
                $map[$resolved['row_key']] = $row;
            }
        }

        return $map;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function syncSeguimientoYiwuNotes(
        Worksheet $sheet,
        int $idContenedor,
        int $snapshotId,
        string $trigger
    ): array {
        $config = (array) config('seguimiento_drive_cells.sheets.Seguimiento.yiwu', []);
        $startCol = (int) ($config['start_col'] ?? 2);
        $noteColIndex = $startCol + (int) data_get($config, 'columns.yiwu_notas.index', 10);
        $codeColIndex = $startCol + (int) data_get($config, 'columns.code_supplier', 3);
        $clienteColIndex = $startCol + (int) data_get($config, 'columns.cliente', 2);

        $highestRow = (int) $sheet->getHighestDataRow();
        $upserted = 0;
        $history = 0;

        for ($row = 1; $row <= $highestRow; $row++) {
            $consLabel = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($startCol) . $row)->getCalculatedValue());
            if ($consLabel !== '' && stripos($consLabel, 'TOTAL EN YIWU') !== false) {
                continue;
            }

            $codeSupplier = trim((string) $this->cellDisplayValue($sheet, $codeColIndex, $row));
            $note = trim((string) $this->cellDisplayValue($sheet, $noteColIndex, $row));
            $cliente = trim((string) $this->cellDisplayValue($sheet, $clienteColIndex, $row));

            if ($codeSupplier === '') {
                continue;
            }

            // Nota vacía: no borrar la guardada en BD (preservar entre syncs).
            if ($note === '') {
                continue;
            }

            $idProveedor = $this->resolveProveedorByCodeAndCliente($idContenedor, $codeSupplier, $cliente);
            if ($idProveedor === null) {
                continue;
            }

            $cellRef = Coordinate::stringFromColumnIndex($noteColIndex) . $row;
            $result = $this->repository->upsertCell([
                'id_contenedor' => $idContenedor,
                'sheet_name' => 'Seguimiento',
                'row_key' => SeguimientoDriveCellRowKey::yiwuProveedor($idProveedor),
                'column_key' => 'yiwu_notas',
                'id_cotizacion' => null,
                'id_proveedor' => $idProveedor,
                'cell_ref' => $cellRef,
                'row_number' => $row,
                'column_letter' => Coordinate::stringFromColumnIndex($noteColIndex),
                'cell_value' => $note,
                'is_manual' => true,
                'change_source' => $trigger,
                'snapshot_id' => $snapshotId,
            ]);

            $upserted++;
            if ($result['changed']) {
                $history++;
            }
        }

        return [$upserted, $history];
    }

    /**
     * @return array{0:int,1:int}
     */
    private function syncSeguimientoContactarNotes(
        Worksheet $sheet,
        int $idContenedor,
        int $snapshotId,
        string $trigger
    ): array {
        $config = (array) config('seguimiento_drive_cells.sheets.Seguimiento.contactar', []);
        $startCol = (int) ($config['start_col'] ?? 22);
        $noteColIndex = $startCol + (int) data_get($config, 'columns.note.index', 6);
        $codeColIndex = $startCol + (int) data_get($config, 'columns.code_supplier', 4);
        $clienteColIndex = $startCol + (int) data_get($config, 'columns.cliente', 2);

        $highestRow = (int) $sheet->getHighestDataRow();
        $upserted = 0;
        $history = 0;

        for ($row = 1; $row <= $highestRow; $row++) {
            $codeSupplier = trim((string) $this->cellDisplayValue($sheet, $codeColIndex, $row));
            $note = trim((string) $this->cellDisplayValue($sheet, $noteColIndex, $row));
            $cliente = trim((string) $this->cellDisplayValue($sheet, $clienteColIndex, $row));

            if ($codeSupplier === '' || $note === '') {
                continue;
            }

            $idProveedor = $this->resolveProveedorByCodeAndCliente($idContenedor, $codeSupplier, $cliente);
            if ($idProveedor === null) {
                continue;
            }

            $cellRef = Coordinate::stringFromColumnIndex($noteColIndex) . $row;
            $result = $this->repository->upsertCell([
                'id_contenedor' => $idContenedor,
                'sheet_name' => 'Seguimiento',
                'row_key' => SeguimientoDriveCellRowKey::contactarProveedor($idProveedor),
                'column_key' => 'note',
                'id_cotizacion' => null,
                'id_proveedor' => $idProveedor,
                'cell_ref' => $cellRef,
                'row_number' => $row,
                'column_letter' => Coordinate::stringFromColumnIndex($noteColIndex),
                'cell_value' => $note,
                'is_manual' => true,
                'change_source' => $trigger,
                'snapshot_id' => $snapshotId,
            ]);

            $upserted++;
            if ($result['changed']) {
                $history++;
            }
        }

        return [$upserted, $history];
    }

    /**
     * @return array{0:int,1:int}
     */
    private function syncSeguimientoUrgenciaNotes(
        Worksheet $sheet,
        int $idContenedor,
        int $snapshotId,
        string $trigger
    ): array {
        $config = (array) config('seguimiento_drive_cells.sheets.Seguimiento.urgencia', []);
        $startCol = (int) ($config['start_col'] ?? 30);
        $noteColIndex = $startCol + (int) data_get($config, 'columns.urgencia_notas.index', 7);
        $clienteColIndex = $startCol + (int) data_get($config, 'columns.cliente', 2);
        $cbmColIndex = $startCol + (int) data_get($config, 'columns.cbm', 3);

        $highestRow = (int) $sheet->getHighestDataRow();
        $upserted = 0;
        $history = 0;

        for ($row = 1; $row <= $highestRow; $row++) {
            $consLabel = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($startCol) . $row)->getCalculatedValue());
            if ($consLabel !== '' && stripos($consLabel, 'TOTAL POR CONTACTAR') !== false) {
                continue;
            }

            $cliente = trim((string) $this->cellDisplayValue($sheet, $clienteColIndex, $row));
            $note = trim((string) $this->cellDisplayValue($sheet, $noteColIndex, $row));
            $cbm = trim((string) $this->cellDisplayValue($sheet, $cbmColIndex, $row));

            if ($cliente === '' || $note === '') {
                continue;
            }

            $idProveedor = $this->resolveUrgenciaProveedor($idContenedor, $cliente, $cbm);
            if ($idProveedor === null) {
                continue;
            }

            $cellRef = Coordinate::stringFromColumnIndex($noteColIndex) . $row;
            $result = $this->repository->upsertCell([
                'id_contenedor' => $idContenedor,
                'sheet_name' => 'Seguimiento',
                'row_key' => SeguimientoDriveCellRowKey::urgenciaProveedor($idProveedor),
                'column_key' => 'urgencia_notas',
                'id_cotizacion' => null,
                'id_proveedor' => $idProveedor,
                'cell_ref' => $cellRef,
                'row_number' => $row,
                'column_letter' => Coordinate::stringFromColumnIndex($noteColIndex),
                'cell_value' => $note,
                'is_manual' => true,
                'change_source' => $trigger,
                'snapshot_id' => $snapshotId,
            ]);

            $upserted++;
            if ($result['changed']) {
                $history++;
            }
        }

        return [$upserted, $history];
    }

    /**
     * @return int|null
     */
    private function resolveUrgenciaProveedor(int $idContenedor, string $cliente, string $cbm): ?int
    {
        $query = DB::table('contenedor_consolidado_cotizacion_proveedores as P')
            ->join('contenedor_consolidado_cotizacion as C', 'C.id', '=', 'P.id_cotizacion')
            ->where('P.id_contenedor', $idContenedor)
            ->whereNull('C.deleted_at')
            ->where('C.nombre', $cliente);

        if ($cbm !== '' && is_numeric($cbm)) {
            $cbmVal = round((float) $cbm, 2);
            $query->where(function ($q) use ($cbmVal) {
                $q->whereRaw('ROUND(COALESCE(P.cbm_total, 0), 2) = ?', [$cbmVal])
                    ->orWhereRaw('ROUND(COALESCE(P.cbm_total_china, 0), 2) = ?', [$cbmVal]);
            });
        }

        $row = $query->orderBy('P.id')->first();

        return $row ? (int) $row->id : null;
    }

    /**
     * @return array{id_cotizacion:int,id_proveedor:?int,row_key:string}|null
     */
    private function resolveCotizacionesRow(int $idContenedor, string $nombre, string $whatsapp, string $codeSupplier)
    {
        $query = DB::table('contenedor_consolidado_cotizacion')
            ->where('id_contenedor', $idContenedor)
            ->whereNull('deleted_at')
            ->where('nombre', $nombre);

        if ($whatsapp !== '') {
            $normalized = preg_replace('/\s+/', '', $whatsapp);
            $query->where(function ($q) use ($whatsapp, $normalized) {
                $q->where('telefono', $whatsapp)
                    ->orWhere('telefono', $normalized)
                    ->orWhereRaw('REPLACE(telefono, " ", "") = ?', [$normalized]);
            });
        }

        $cotizacion = $query->orderBy('id')->first();
        if (!$cotizacion) {
            return null;
        }

        $idCotizacion = (int) $cotizacion->id;
        $idProveedor = null;

        if ($codeSupplier !== '') {
            $proveedor = DB::table('contenedor_consolidado_cotizacion_proveedores')
                ->where('id_cotizacion', $idCotizacion)
                ->where('code_supplier', $codeSupplier)
                ->orderBy('id')
                ->first();

            if ($proveedor) {
                $idProveedor = (int) $proveedor->id;
            }
        }

        return [
            'id_cotizacion' => $idCotizacion,
            'id_proveedor' => $idProveedor,
            'row_key' => SeguimientoDriveCellRowKey::cotizaciones($idCotizacion, $idProveedor),
        ];
    }

    private function resolveProveedorByCodeAndCliente(int $idContenedor, string $codeSupplier, string $cliente): ?int
    {
        if ($cliente !== '') {
            $withCliente = DB::table('contenedor_consolidado_cotizacion_proveedores as P')
                ->join('contenedor_consolidado_cotizacion as C', 'C.id', '=', 'P.id_cotizacion')
                ->where('P.id_contenedor', $idContenedor)
                ->where('P.code_supplier', $codeSupplier)
                ->whereNull('C.deleted_at')
                ->where('C.nombre', $cliente)
                ->orderBy('P.id')
                ->first();

            if ($withCliente) {
                return (int) $withCliente->id;
            }
        }

        // Fallback: code en el consolidado (CLIENTE puede estar vacío por merge).
        $row = DB::table('contenedor_consolidado_cotizacion_proveedores as P')
            ->join('contenedor_consolidado_cotizacion as C', 'C.id', '=', 'P.id_cotizacion')
            ->where('P.id_contenedor', $idContenedor)
            ->where('P.code_supplier', $codeSupplier)
            ->whereNull('C.deleted_at')
            ->orderBy('P.id')
            ->first();

        return $row ? (int) $row->id : null;
    }

    /**
     * Reaplica notas manuales de Seguimiento (YIWU / CONTACTAR / URGENCIA) al Excel regenerado.
     */
    private function applyManualSeguimientoNotes(Worksheet $sheet, int $idContenedor): bool
    {
        $changed = false;
        $changed = $this->applyManualYiwuNotesFromDb($sheet, $idContenedor) || $changed;
        $changed = $this->applyManualContactarNotesFromDb($sheet, $idContenedor) || $changed;
        $changed = $this->applyManualUrgenciaNotesFromDb($sheet, $idContenedor) || $changed;

        return $changed;
    }

    private function applyManualYiwuNotesFromDb(Worksheet $sheet, int $idContenedor): bool
    {
        $notes = $this->repository->manualValuesByColumn($idContenedor, 'Seguimiento', 'yiwu_notas');
        if ($notes === []) {
            return false;
        }

        $config = (array) config('seguimiento_drive_cells.sheets.Seguimiento.yiwu', []);
        $startCol = (int) ($config['start_col'] ?? 2);
        $noteColIndex = $startCol + (int) data_get($config, 'columns.yiwu_notas.index', 10);
        $codeColIndex = $startCol + (int) data_get($config, 'columns.code_supplier', 3);
        $clienteColIndex = $startCol + (int) data_get($config, 'columns.cliente', 2);
        $highestRow = (int) $sheet->getHighestDataRow();
        $changed = false;

        for ($row = 1; $row <= $highestRow; $row++) {
            $codeSupplier = trim((string) $this->cellDisplayValue($sheet, $codeColIndex, $row));
            if ($codeSupplier === '') {
                continue;
            }

            $cliente = trim((string) $this->cellDisplayValue($sheet, $clienteColIndex, $row));
            $idProveedor = $this->resolveProveedorByCodeAndCliente($idContenedor, $codeSupplier, $cliente);
            if ($idProveedor === null) {
                continue;
            }

            $rowKey = SeguimientoDriveCellRowKey::yiwuProveedor($idProveedor);
            if (!isset($notes[$rowKey]) || $notes[$rowKey] === '') {
                continue;
            }

            $cellRef = Coordinate::stringFromColumnIndex($noteColIndex) . $row;
            $current = trim((string) $sheet->getCell($cellRef)->getValue());
            if ($current === $notes[$rowKey]) {
                continue;
            }

            $sheet->setCellValue($cellRef, $notes[$rowKey]);
            $changed = true;
        }

        return $changed;
    }

    private function applyManualContactarNotesFromDb(Worksheet $sheet, int $idContenedor): bool
    {
        $notes = $this->repository->manualValuesByColumn($idContenedor, 'Seguimiento', 'note');
        if ($notes === []) {
            return false;
        }

        $config = (array) config('seguimiento_drive_cells.sheets.Seguimiento.contactar', []);
        $startCol = (int) ($config['start_col'] ?? 22);
        $noteColIndex = $startCol + (int) data_get($config, 'columns.note.index', 6);
        $codeColIndex = $startCol + (int) data_get($config, 'columns.code_supplier', 4);
        $clienteColIndex = $startCol + (int) data_get($config, 'columns.cliente', 2);
        $highestRow = (int) $sheet->getHighestDataRow();
        $changed = false;

        for ($row = 1; $row <= $highestRow; $row++) {
            $codeSupplier = trim((string) $this->cellDisplayValue($sheet, $codeColIndex, $row));
            if ($codeSupplier === '') {
                continue;
            }

            $cliente = trim((string) $this->cellDisplayValue($sheet, $clienteColIndex, $row));
            $idProveedor = $this->resolveProveedorByCodeAndCliente($idContenedor, $codeSupplier, $cliente);
            if ($idProveedor === null) {
                continue;
            }

            $rowKey = SeguimientoDriveCellRowKey::contactarProveedor($idProveedor);
            if (!isset($notes[$rowKey]) || $notes[$rowKey] === '') {
                continue;
            }

            $cellRef = Coordinate::stringFromColumnIndex($noteColIndex) . $row;
            $current = trim((string) $sheet->getCell($cellRef)->getValue());
            if ($current === $notes[$rowKey]) {
                continue;
            }

            $sheet->setCellValue($cellRef, $notes[$rowKey]);
            $changed = true;
        }

        return $changed;
    }

    private function applyManualUrgenciaNotesFromDb(Worksheet $sheet, int $idContenedor): bool
    {
        $notes = $this->repository->manualValuesByColumn($idContenedor, 'Seguimiento', 'urgencia_notas');
        if ($notes === []) {
            return false;
        }

        $config = (array) config('seguimiento_drive_cells.sheets.Seguimiento.urgencia', []);
        $startCol = (int) ($config['start_col'] ?? 30);
        $noteColIndex = $startCol + (int) data_get($config, 'columns.urgencia_notas.index', 7);
        $clienteColIndex = $startCol + (int) data_get($config, 'columns.cliente', 2);
        $cbmColIndex = $startCol + (int) data_get($config, 'columns.cbm', 3);
        $highestRow = (int) $sheet->getHighestDataRow();
        $changed = false;

        for ($row = 1; $row <= $highestRow; $row++) {
            $cliente = trim((string) $this->cellDisplayValue($sheet, $clienteColIndex, $row));
            $cbm = trim((string) $this->cellDisplayValue($sheet, $cbmColIndex, $row));
            if ($cliente === '') {
                continue;
            }

            $idProveedor = $this->resolveUrgenciaProveedor($idContenedor, $cliente, $cbm);
            if ($idProveedor === null) {
                continue;
            }

            $rowKey = SeguimientoDriveCellRowKey::urgenciaProveedor($idProveedor);
            if (!isset($notes[$rowKey]) || $notes[$rowKey] === '') {
                continue;
            }

            $cellRef = Coordinate::stringFromColumnIndex($noteColIndex) . $row;
            $current = trim((string) $sheet->getCell($cellRef)->getValue());
            if ($current === $notes[$rowKey]) {
                continue;
            }

            $sheet->setCellValue($cellRef, $notes[$rowKey]);
            $changed = true;
        }

        return $changed;
    }

    /**
     * Valor visible de celda (si está en merge, lee la celda master).
     *
     * @return mixed
     */
    private function cellDisplayValue(Worksheet $sheet, int $colIndex, int $row)
    {
        $cellRef = Coordinate::stringFromColumnIndex($colIndex) . $row;
        $cell = $sheet->getCell($cellRef);
        if ($cell->isInMergeRange()) {
            $mergeRange = $cell->getMergeRange();
            if (is_string($mergeRange) && $mergeRange !== '') {
                $boundaries = Coordinate::rangeBoundaries($mergeRange);
                // PhpSpreadsheet 1.30+ devuelve índice numérico de columna (ej. 21), no letra (U).
                $masterCol = $boundaries[0][0] ?? null;
                $masterRow = (int) ($boundaries[0][1] ?? 0);
                if ($masterCol !== null && $masterRow > 0) {
                    $masterColLetter = is_numeric($masterCol)
                        ? Coordinate::stringFromColumnIndex((int) $masterCol)
                        : (string) $masterCol;

                    return $sheet->getCell($masterColLetter . $masterRow)->getCalculatedValue();
                }
            }
        }

        return $cell->getCalculatedValue();
    }
}
