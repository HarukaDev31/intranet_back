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
            ]);

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
        $noteColIndex = $startCol + 8;
        $codeColIndex = $startCol + 3;
        $clienteColIndex = $startCol + 2;

        $highestRow = (int) $sheet->getHighestDataRow();
        $upserted = 0;
        $history = 0;

        for ($row = 1; $row <= $highestRow; $row++) {
            $consLabel = trim((string) $sheet->getCellByColumnAndRow($startCol, $row)->getCalculatedValue());
            if ($consLabel !== '' && stripos($consLabel, 'TOTAL EN YIWU') !== false) {
                continue;
            }

            $codeSupplier = trim((string) $sheet->getCellByColumnAndRow($codeColIndex, $row)->getCalculatedValue());
            $note = trim((string) $sheet->getCellByColumnAndRow($noteColIndex, $row)->getCalculatedValue());
            $cliente = trim((string) $sheet->getCellByColumnAndRow($clienteColIndex, $row)->getCalculatedValue());

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
        $startCol = (int) ($config['start_col'] ?? 20);
        $width = (int) ($config['width'] ?? 6);
        $noteColIndex = $startCol + 5;
        $codeColIndex = $startCol + 4;
        $clienteColIndex = $startCol + 2;

        $highestRow = (int) $sheet->getHighestDataRow();
        $upserted = 0;
        $history = 0;

        for ($row = 1; $row <= $highestRow; $row++) {
            $codeSupplier = trim((string) $sheet->getCellByColumnAndRow($codeColIndex, $row)->getCalculatedValue());
            $note = trim((string) $sheet->getCellByColumnAndRow($noteColIndex, $row)->getCalculatedValue());
            $cliente = trim((string) $sheet->getCellByColumnAndRow($clienteColIndex, $row)->getCalculatedValue());

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
        $query = DB::table('contenedor_consolidado_cotizacion_proveedores as P')
            ->join('contenedor_consolidado_cotizacion as C', 'C.id', '=', 'P.id_cotizacion')
            ->where('P.id_contenedor', $idContenedor)
            ->where('P.code_supplier', $codeSupplier)
            ->whereNull('C.deleted_at');

        if ($cliente !== '') {
            $query->where('C.nombre', $cliente);
        }

        $row = $query->orderBy('P.id')->first();

        return $row ? (int) $row->id : null;
    }
}
