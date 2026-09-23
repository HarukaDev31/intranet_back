<?php

namespace App\Services\CargaConsolidada;

use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Traits\GoogleSheetsHelper;
use App\Traits\UsesObjectStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Códigos VIN/VIM de movilidad personal: correlativos, Google Sheet y plantilla Excel.
 */
class MovilidadPersonalVimService
{
    use GoogleSheetsHelper;
    use UsesObjectStorage;

    /**
     * Genera correlativos, los escribe en el sheet y arma el Excel VIM.
     *
     * @param array<string, mixed> $proveedor
     * @return array{codes: list<string>, excel_path: string, qty: int}|null
     */
    public function generateForProveedor(Cotizacion $cotizacion, array $proveedor, $carga, $qtyHint = 0): ?array
    {
        $supplierCode = (string) ($proveedor['code_supplier'] ?? '');
        $proveedorId = (int) ($proveedor['id'] ?? 0);

        $proveedorDb = null;
        if ($proveedorId > 0) {
            $proveedorDb = CotizacionProveedor::where('id', $proveedorId)
                ->where('id_cotizacion', $cotizacion->id)
                ->first();
        }
        if (!$proveedorDb && $supplierCode !== '') {
            $proveedorDb = CotizacionProveedor::where('code_supplier', $supplierCode)
                ->where('id_cotizacion', $cotizacion->id)
                ->first();
        }
        if (!$proveedorDb) {
            Log::error('MovilidadPersonalVimService: proveedor no encontrado', [
                'id_cotizacion' => $cotizacion->id,
                'id' => $proveedorId,
                'code_supplier' => $supplierCode,
            ]);

            return null;
        }

        $qtyBox = $this->resolveQty($proveedor, $proveedorDb, $qtyHint);
        if ($qtyBox <= 0) {
            Log::warning('MovilidadPersonalVimService: qty no válido para movilidad personal', [
                'id_proveedor' => $proveedorDb->id,
                'code_supplier' => $supplierCode,
            ]);

            return null;
        }

        Log::info('MovilidadPersonalVimService: procesando', [
            'qty_box' => $qtyBox,
            'cliente' => $cotizacion->nombre,
            'code_supplier' => $supplierCode,
        ]);

        $lastRowData = $this->getLastRowWithVinCodeInColumnF();
        if (!$lastRowData || empty($lastRowData['code'])) {
            Log::error('MovilidadPersonalVimService: no se obtuvo última fila con VIN en columna F');

            return null;
        }

        $lastCode = $lastRowData['code'];
        $lastRowNumber = (int) $lastRowData['row'];
        $codes = $this->generateCorrelativeCodes($lastCode, $qtyBox);
        if ($codes === [] || count($codes) !== $qtyBox) {
            Log::error('MovilidadPersonalVimService: códigos inválidos; se omite Google Sheet y VIM', [
                'last_code' => $lastCode,
                'qty_box' => $qtyBox,
                'codes_count' => count($codes),
            ]);

            return null;
        }

        $sheetName = $cotizacion->nombre . ' CONS' . $carga;
        $this->addRowsToGoogleSheet($sheetName, $codes, $qtyBox, $lastRowNumber);

        $excelPath = $this->processVimTemplate((string) $cotizacion->nombre, $codes);
        if (!$excelPath || !is_file($excelPath)) {
            Log::error('MovilidadPersonalVimService: no se pudo crear el archivo VIM');

            return null;
        }

        return [
            'codes' => $codes,
            'excel_path' => $excelPath,
            'qty' => $qtyBox,
        ];
    }

    public function ejemploPdfPath(): ?string
    {
        $path = $this->storageLocalPath('templates/rotulado/movilidad_personal.pdf');

        return is_file($path) ? $path : null;
    }

    /**
     * @param array<string, mixed> $proveedor
     */
    private function resolveQty(array $proveedor, CotizacionProveedor $proveedorDb, $qtyHint): int
    {
        $candidates = [
            $qtyHint,
            $proveedor['total_initial_qty_movilidad_personal'] ?? null,
            $proveedor['qty_box'] ?? null,
            $proveedorDb->qty_box ?? null,
        ];

        foreach ($candidates as $candidate) {
            $qty = (int) $candidate;
            if ($qty > 0) {
                return $qty;
            }
        }

        $items = DB::table('contenedor_consolidado_cotizacion_proveedores_items')
            ->where('id_proveedor', $proveedorDb->id)
            ->sum('initial_qty');

        return (int) ($items ?? 0);
    }

    /**
     * @return array{row: int, code: string, rowData: mixed}|null
     */
    private function getLastRowWithVinCodeInColumnF(): ?array
    {
        try {
            $values = $this->getRangeValues('A1:Z2000');
            if (empty($values)) {
                return null;
            }

            $lastRowIndex = -1;
            $lastCode = null;
            $lastRowData = null;

            foreach ($values as $rowIndex => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $codeF = isset($row[5]) ? trim((string) $row[5]) : '';
                if ($codeF === '') {
                    continue;
                }
                if (!preg_match('/^L7NES\d+[A-Z]{3}\d+$/i', $codeF)) {
                    continue;
                }

                $lastRowIndex = $rowIndex;
                $lastCode = $codeF;
                $lastRowData = $row;
            }

            if ($lastRowIndex < 0 || $lastCode === null) {
                Log::warning('MovilidadPersonalVimService: no hay VIN válido en columna F (formato L7NES…)');

                return null;
            }

            return [
                'row' => $lastRowIndex + 1,
                'code' => $lastCode,
                'rowData' => $lastRowData,
            ];
        } catch (\Exception $e) {
            Log::error('MovilidadPersonalVimService: error leyendo VIN en F: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function generateCorrelativeCodes($baseCode, int $qtyBox): array
    {
        try {
            $baseCode = trim((string) $baseCode);
            if (!preg_match('/^L7NES(\d+)([A-Z]{3})(\d+)$/i', $baseCode, $matches)) {
                throw new \Exception('Formato de código base inválido: ' . $baseCode);
            }

            $lote = $matches[1];
            $trigrama = strtoupper($matches[2]);
            $startNumber = ((int) $matches[3]) + 1;
            $codes = [];

            for ($i = 0; $i < $qtyBox; $i++) {
                $codes[] = sprintf('L7NES%s%s%06d', $lote, $trigrama, $startNumber + $i);
            }

            Log::info('MovilidadPersonalVimService: códigos generados', ['codes' => $codes]);

            return $codes;
        } catch (\Exception $e) {
            Log::error('MovilidadPersonalVimService: error generando correlativos: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @param list<string> $codes
     */
    private function addRowsToGoogleSheet($clienteNombre, array $codes, int $qtyBox, int $lastRowWithData): void
    {
        try {
            if ($qtyBox < 1 || count($codes) !== $qtyBox) {
                throw new \InvalidArgumentException(
                    'addRowsToGoogleSheet: se requiere un código por fila (qty=' . $qtyBox . ', códigos=' . count($codes) . ')'
                );
            }

            $startRow = $lastRowWithData + 1;
            $endRow = $startRow + $qtyBox - 1;
            $this->ensureSheetRowCapacity($endRow);

            $bData = [];
            $cData = [];
            $fData = [];
            for ($i = 0; $i < $qtyBox; $i++) {
                $bData[] = [$clienteNombre];
                $cData[] = [$i + 1];
                $fData[] = [$codes[$i]];
            }

            $this->insertRangeValues("B{$startRow}:B{$endRow}", $bData);
            $this->insertRangeValues("C{$startRow}:C{$endRow}", $cData);
            $this->insertRangeValues("F{$startRow}:F{$endRow}", $fData);

            if ($qtyBox > 1) {
                $this->mergeCells("B{$startRow}", "B{$endRow}");
            }
            $this->applyBordersToRows($startRow, $endRow, 'B', 'G');

            Log::info("MovilidadPersonalVimService: filas agregadas al sheet desde {$startRow}");
        } catch (\Exception $e) {
            Log::error('MovilidadPersonalVimService: error escribiendo Google Sheet: ' . $e->getMessage());
        }
    }

    /**
     * @param list<string> $codes
     */
    private function processVimTemplate(string $clienteNombre, array $codes): ?string
    {
        try {
            $templatePath = public_path('assets/templates/PlantillaVim.xlsx');
            if (!file_exists($templatePath)) {
                throw new \Exception('Plantilla VIM no encontrada: ' . $templatePath);
            }

            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempPath = $tempDir . '/vim_' . time() . '_' . uniqid() . '.xlsx';
            copy($templatePath, $tempPath);

            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($tempPath);
            $worksheet = $spreadsheet->getActiveSheet();

            $startRow = 3;
            $endRow = $startRow + count($codes) - 1;

            foreach ($codes as $index => $code) {
                $row = $startRow + $index;
                $worksheet->setCellValue("B{$row}", $clienteNombre);
                $worksheet->setCellValue("C{$row}", $index + 1);
                $worksheet->setCellValue("F{$row}", $code);
            }

            $worksheet->getColumnDimension('F')->setWidth(30);

            try {
                $worksheet->unmergeCells("B{$startRow}:B{$endRow}");
            } catch (\Exception $e) {
                Log::info('MovilidadPersonalVimService: sin celdas mergeadas en B' . $startRow . ':B' . $endRow);
            }

            if (count($codes) > 1) {
                $worksheet->mergeCells("B{$startRow}:B{$endRow}");
                $worksheet->getStyle("B{$startRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }

            $borderStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ];
            $worksheet->getStyle("B{$startRow}:G{$endRow}")->applyFromArray($borderStyle);

            $fontStyle = [
                'font' => [
                    'bold' => true,
                    'size' => 15,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ];
            $worksheet->getStyle("C{$startRow}:C{$endRow}")->applyFromArray($fontStyle);
            $worksheet->getStyle("F{$startRow}:F{$endRow}")->applyFromArray($fontStyle);

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($tempPath);

            Log::info('MovilidadPersonalVimService: plantilla VIM procesada: ' . $tempPath);

            return $tempPath;
        } catch (\Exception $e) {
            Log::error('MovilidadPersonalVimService: error procesando plantilla VIM: ' . $e->getMessage());

            return null;
        }
    }

    private function applyBordersToRows(int $startRow, int $endRow, string $startColumn = 'A', ?string $endColumn = null): void
    {
        try {
            if (!$this->initializeGoogleSheets()) {
                throw new \Exception('No se pudo inicializar Google Sheets');
            }

            $sheetId = $this->getSheetId();
            $endColumnIndex = $this->letterToColumnIndex($endColumn ?: 'G') + 1;
            $startColumnIndex = $this->letterToColumnIndex($startColumn);

            $range = new \Google\Service\Sheets\GridRange([
                'sheetId' => $sheetId,
                'startRowIndex' => $startRow - 1,
                'endRowIndex' => $endRow,
                'startColumnIndex' => $startColumnIndex,
                'endColumnIndex' => $endColumnIndex,
            ]);

            $borderStyle = new \Google\Service\Sheets\Border([
                'style' => 'SOLID',
                'width' => 1,
                'color' => new \Google\Service\Sheets\Color([
                    'red' => 0.0,
                    'green' => 0.0,
                    'blue' => 0.0,
                ]),
            ]);

            $borders = new \Google\Service\Sheets\Borders([
                'top' => $borderStyle,
                'bottom' => $borderStyle,
                'left' => $borderStyle,
                'right' => $borderStyle,
            ]);

            $formatRequest = new \Google\Service\Sheets\Request([
                'repeatCell' => new \Google\Service\Sheets\RepeatCellRequest([
                    'range' => $range,
                    'cell' => new \Google\Service\Sheets\CellData([
                        'userEnteredFormat' => new \Google\Service\Sheets\CellFormat([
                            'borders' => $borders,
                        ]),
                    ]),
                    'fields' => 'userEnteredFormat.borders',
                ]),
            ]);

            $this->googleService->spreadsheets->batchUpdate(
                $this->spreadsheetId,
                new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                    'requests' => [$formatRequest],
                ])
            );

            Log::info("MovilidadPersonalVimService: bordes aplicados {$startRow}-{$endRow}");
        } catch (\Exception $e) {
            Log::error("MovilidadPersonalVimService: error aplicando bordes: " . $e->getMessage());
        }
    }
}
