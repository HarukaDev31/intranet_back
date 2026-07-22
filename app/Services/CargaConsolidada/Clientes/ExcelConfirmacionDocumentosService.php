<?php

namespace App\Services\CargaConsolidada\Clientes;

use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Traits\UsesObjectStorage;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as SharedDrawing;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ZipArchive;

class ExcelConfirmacionDocumentosService
{
    use UsesObjectStorage;
    private const CAMPO_NOMBRE_COMERCIAL = 'NOMBRE COMERCIAL';

    private const CAMPO_FOTO = 'FOTO/IMAGEN';

    private const CAMPO_HS_CODE = 'HS CODE (Solicitar al Proveedor)';

    private const CAMPO_LINK = 'LINK DEL PRODUCTO';

    /** @var list<string> */
    private const CAMPOS_FIJOS_EXCEL = [
        self::CAMPO_NOMBRE_COMERCIAL,
        self::CAMPO_FOTO,
        self::CAMPO_HS_CODE,
        self::CAMPO_LINK,
    ];

    /**
     * Genera y guarda un Excel de confirmación para un proveedor a partir de la plantilla OOXML.
     * Post-procesa el zip para tamaño/recorte del logo igual que la plantilla.
     */
    public function generarArchivoPorProveedor(string $templatePath, string $outputFullPath, array $proveedorPayload): bool
    {
        $cotizacionProveedor = CotizacionProveedor::where('id', $proveedorPayload['id'] ?? null)->first();
        $codeSupplier = $cotizacionProveedor ? (string) ($cotizacionProveedor->code_supplier ?? '') : '';
        $items = $proveedorPayload['items'] ?? [];

        if (!file_exists($templatePath)) {
            Log::error('Plantilla de Excel de confirmación no encontrada: ' . $templatePath);

            return false;
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        $labelsMap = $this->labelsPorTipoProducto();
        $baseStartRow = 14;
        $baseBlockRows = 12;

        $currentRow = $baseStartRow;
        foreach ($items as $idx => $item) {
            $tipo = strtoupper($item['tipo_producto'] ?? 'GENERAL');
            $labels = $labelsMap[$tipo] ?? $labelsMap['GENERAL'];
            $rowsNeeded = max($baseBlockRows, count($labels));

            if ($idx === 0) {
                if ($rowsNeeded > $baseBlockRows) {
                    if ($tipo === 'MOVILIDAD PERSONAL') {
                        foreach (['B', 'C', 'D', 'F', 'G', 'H', 'I', 'J', 'K', 'L'] as $colToUnmerge) {
                            $sheet->unmergeCells("{$colToUnmerge}{$baseStartRow}:{$colToUnmerge}" . ($baseStartRow + $baseBlockRows - 1));
                        }
                    }
                    $sheet->insertNewRowBefore($baseStartRow + $baseBlockRows, $rowsNeeded - $baseBlockRows);
                    for ($r = 0; $r < ($rowsNeeded - $baseBlockRows); $r++) {
                        $srcRow = $baseStartRow + $baseBlockRows - 1;
                        $dstRow = $baseStartRow + $baseBlockRows + $r;
                        $sheet->duplicateStyle($sheet->getStyle("B{$srcRow}:L{$srcRow}"), "B{$dstRow}:L{$dstRow}");
                    }
                }
                $startRow = $baseStartRow;
            } else {
                $sheet->insertNewRowBefore($currentRow, $rowsNeeded);
                for ($r = 0; $r < $rowsNeeded; $r++) {
                    $srcRow = $baseStartRow + min($r, $baseBlockRows - 1);
                    $dstRow = $currentRow + $r;
                    $sheet->duplicateStyle($sheet->getStyle("B{$srcRow}:L{$srcRow}"), "B{$dstRow}:L{$dstRow}");
                }
                $startRow = $currentRow;
            }

            foreach (range('B', 'L') as $colRef) {
                for ($rowApply = $startRow; $rowApply <= ($startRow + $rowsNeeded - 1); $rowApply++) {
                    $sheet->duplicateStyle($sheet->getStyle("{$colRef}14"), "{$colRef}{$rowApply}");
                }
            }

            for ($rowApply = $startRow; $rowApply <= ($startRow + $rowsNeeded - 1); $rowApply++) {
                $borders = $sheet->getStyle('A' . $rowApply)->getBorders();
                $borders->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
                $borders->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
                $borders->getLeft()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
                $borders->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
            }

            $blockLastRow = $startRow + $rowsNeeded - 1;
            $this->removeConflictingMergedCellsBeforeExcelConfirmacionBlock(
                $sheet,
                $startRow,
                $blockLastRow,
                'B',
                'L'
            );

            foreach (range('B', 'L') as $col) {
                if ($col === 'E') {
                    continue;
                }
                $sheet->mergeCells("{$col}{$startRow}:{$col}" . ($startRow + $rowsNeeded - 1));
            }

            $endRow = $startRow + $rowsNeeded - 1;
            $sheet->duplicateStyle($sheet->getStyle('H14'), "H{$startRow}:H{$endRow}");
            $sheet->duplicateStyle($sheet->getStyle('G14'), "G{$startRow}:G{$endRow}");

            $sheet->setCellValue('I' . $startRow, '=G' . $startRow . '*H' . $startRow);

            $sheet->duplicateStyle($sheet->getStyle('J14'), "J{$startRow}:J{$endRow}");
            $sheet->setCellValueExplicit(
                'J' . $startRow,
                (string) $codeSupplier,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );

            $caracteristicas = $this->mergeUnidadesEnCaracteristicas(
                is_array($item['caracteristicas'] ?? null) ? $item['caracteristicas'] : []
            );
            $qty = $item['qty'] ?? $item['initial_qty'] ?? null;
            $precio = $item['precio_unitario'] ?? $item['initial_price'] ?? null;

            $nombreComercial = $this->resolveCaracteristicaValue($caracteristicas, self::CAMPO_NOMBRE_COMERCIAL);
            if ($nombreComercial === '') {
                $nombreComercial = (string) ($item['initial_name'] ?? '');
            }

            $sheet->setCellValueExplicit('B' . $startRow, (string) ($idx + 1), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->duplicateStyle($sheet->getStyle('B14'), 'B' . $startRow . ':B' . $endRow);

            $sheet->setCellValueExplicit('C' . $startRow, $nombreComercial, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->duplicateStyle($sheet->getStyle('C14'), 'C' . $startRow . ':C' . $endRow);
            $sheet->getStyle('C' . $startRow . ':C' . $endRow)
                ->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                ->setWrapText(true);

            $foto = $this->resolveCaracteristicaValue($caracteristicas, self::CAMPO_FOTO);
            if ($foto !== '') {
                $this->embedFotoEnCelda($sheet, 'D' . $startRow, $foto, $rowsNeeded);
                $sheet->duplicateStyle($sheet->getStyle('D14'), 'D' . $startRow . ':D' . $endRow);
            }

            if ($qty !== null && $qty !== '') {
                $sheet->setCellValue('G' . $startRow, is_numeric($qty) ? (float) $qty : $qty);
            }

            if ($precio !== null && $precio !== '') {
                $sheet->setCellValue('H' . $startRow, is_numeric($precio) ? (float) $precio : $precio);
            }

            $hsCode = $this->resolveCaracteristicaValue($caracteristicas, self::CAMPO_HS_CODE);
            if ($hsCode !== '') {
                $sheet->setCellValueExplicit('K' . $startRow, $hsCode, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->duplicateStyle($sheet->getStyle('K14'), 'K' . $startRow . ':K' . $endRow);
            }

            $linkProducto = $this->resolveCaracteristicaValue($caracteristicas, self::CAMPO_LINK);
            if ($linkProducto !== '') {
                $sheet->setCellValueExplicit('L' . $startRow, $linkProducto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->duplicateStyle($sheet->getStyle('L14'), 'L' . $startRow . ':L' . $endRow);
            }

            for ($i = 0; $i < $rowsNeeded; $i++) {
                $label = (string) ($labels[$i] ?? '');
                if ($label === '' || $this->isCampoFijoExcel($label) || $this->isUnidadCompanionLabel($label)) {
                    continue;
                }

                $value = $this->resolveCaracteristicaValue($caracteristicas, $label);
                $cellValue = $this->formatCaracteristicaCell($label, $value, $caracteristicas);
                $sheet->setCellValueExplicit('E' . ($startRow + $i), $cellValue, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            for ($i = 0; $i < $rowsNeeded; $i++) {
                $sheet->setCellValueExplicit('F' . ($startRow + $i), $tipo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }

            $currentRow = $startRow + $rowsNeeded;
        }

        $lastEndRow = $currentRow - 1;
        $totalRow = $currentRow;
        if ($lastEndRow >= $baseStartRow) {
            $sheet->setCellValue('G' . $totalRow, '=SUM(G' . $baseStartRow . ':G' . $lastEndRow . ')');
            $sheet->setCellValue('I' . $totalRow, '=SUM(I' . $baseStartRow . ':I' . $lastEndRow . ')');
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($outputFullPath);
        $this->restoreExcelDrawingExtentsFromTemplate($templatePath, $outputFullPath);

        return true;
    }

    /**
     * Genera un único Excel con todos los proveedores, uno por hoja.
     *
     * @param  array<int, array<string, mixed>>  $proveedoresPayloads
     */
    public function generarArchivoGeneralPorCotizacion(
        string $templatePath,
        string $outputFullPath,
        array $proveedoresPayloads
    ): bool {
        if ($proveedoresPayloads === []) {
            return false;
        }

        $tempDir = dirname($outputFullPath);
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $tempFiles = [];

        try {
            $firstPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('excel_conf_general_', true) . '_0.xlsx';
            if (!$this->generarArchivoPorProveedor($templatePath, $firstPath, $proveedoresPayloads[0])) {
                return false;
            }
            $tempFiles[] = $firstPath;

            $spreadsheet = IOFactory::load($firstPath);
            $spreadsheet->getActiveSheet()->setTitle(
                $this->sanitizeExcelSheetTitle(
                    (string) ($proveedoresPayloads[0]['code_supplier'] ?? 'Proveedor_1'),
                    $spreadsheet
                )
            );

            for ($i = 1; $i < count($proveedoresPayloads); $i++) {
                $partPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('excel_conf_general_', true) . "_{$i}.xlsx";
                if (!$this->generarArchivoPorProveedor($templatePath, $partPath, $proveedoresPayloads[$i])) {
                    Log::warning('Excel confirmación general: no se pudo generar hoja de proveedor', [
                        'index' => $i,
                        'proveedor_id' => $proveedoresPayloads[$i]['id'] ?? null,
                    ]);
                    continue;
                }
                $tempFiles[] = $partPath;

                $partSpreadsheet = IOFactory::load($partPath);
                $partSheet = $partSpreadsheet->getActiveSheet();
                $partSheet->setTitle(
                    $this->sanitizeExcelSheetTitle(
                        (string) ($proveedoresPayloads[$i]['code_supplier'] ?? 'Proveedor_' . ($i + 1)),
                        $spreadsheet
                    )
                );
                $spreadsheet->addExternalSheet($partSheet);
            }

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($outputFullPath);
            $this->restoreExcelDrawingExtentsFromTemplate($templatePath, $outputFullPath);

            return is_file($outputFullPath);
        } finally {
            foreach ($tempFiles as $file) {
                @unlink($file);
            }
        }
    }

    private function sanitizeExcelSheetTitle(string $title, ?Spreadsheet $spreadsheet = null): string
    {
        $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', trim($title));
        $title = mb_substr($title !== '' ? $title : 'Proveedor', 0, 31);

        if (!$spreadsheet instanceof Spreadsheet) {
            return $title;
        }

        $base = $title;
        $suffix = 1;
        while ($this->excelSheetTitleExists($spreadsheet, $title)) {
            $suffixStr = '_' . $suffix;
            $title = mb_substr($base, 0, max(1, 31 - mb_strlen($suffixStr))) . $suffixStr;
            $suffix++;
        }

        return $title;
    }

    private function excelSheetTitleExists(Spreadsheet $spreadsheet, string $title): bool
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if ($sheet->getTitle() === $title) {
                return true;
            }
        }

        return false;
    }

    /**
     * Patrón OOXML vía ZipArchive + DOM: copia entrada y sólo cambia celdas indicadas por ref Excel (ej. A1).
     */
    public function modificarPlantilla(string $inputPath, string $outputPath, array $cambios): void
    {
        copy($inputPath, $outputPath);

        $zip = new ZipArchive();
        if ($zip->open($outputPath) !== true) {
            return;
        }

        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($ssXml === false || $sheetXml === false) {
            $zip->close();
            return;
        }

        $domSS = new \DOMDocument();
        $domSS->loadXML($ssXml);
        $xpathSS = new \DOMXPath($domSS);
        $xpathSS->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $domSheet = new \DOMDocument();
        $domSheet->loadXML($sheetXml);
        $xpathSheet = new \DOMXPath($domSheet);
        $xpathSheet->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        foreach ($cambios as $ref => $nuevoValor) {
            $nodo = $xpathSheet->query("//x:c[@r='{$ref}']")->item(0);
            if (!($nodo instanceof \DOMElement)) {
                continue;
            }

            $tipo = $nodo->getAttribute('t');

            if ($tipo === 's') {
                $vItem = $xpathSheet->query('x:v', $nodo)->item(0);
                if (!$vItem) {
                    continue;
                }
                $idx = (int) $vItem->nodeValue;
                $tNodo = $xpathSS->query('//x:si[' . ($idx + 1) . ']/x:t')->item(0);
                if ($tNodo) {
                    $tNodo->nodeValue = $nuevoValor;
                }
            } else {
                $vNodo = $xpathSheet->query('x:v', $nodo)->item(0);
                if ($vNodo) {
                    $vNodo->nodeValue = $nuevoValor;
                }
            }
        }

        $zip->addFromString('xl/sharedStrings.xml', $domSS->saveXML());
        $zip->addFromString('xl/worksheets/sheet1.xml', $domSheet->saveXML());
        $zip->close();
    }

    /**
     * Labels por tipo de producto (fuente de verdad para Excel y formulario web).
     *
     * @return array<string, array<int|string, string>>
     */
    public function getLabelsPorTipoProducto(): array
    {
        return $this->labelsPorTipoProducto();
    }

    /**
     * Labels filtradas (sin strings vacíos) para el formulario web.
     *
     * @return array<string, array<int, string>>
     */
    public function getLabelsPorTipoProductoFiltradas(): array
    {
        $result = [];
        foreach ($this->labelsPorTipoProducto() as $tipo => $labels) {
            $result[$tipo] = array_values(array_filter($labels, static fn ($label) => trim((string) $label) !== ''));
        }

        return $result;
    }

    /**
     * @return array<string, array<int|string, string>>
     */
    private function labelsPorTipoProducto(): array
    {
        return [
            'GENERAL' => [
                'Material:',
                'Marca:',
                'Modelo:',
                'Tamaño (Producto):',
                'Capacidad:',
                'Peso Neto (Producto):',
                'Incluye:',
                'Unidad de medida:',
                'Funcion:',
                'Presentacion (botella, caja, etc.)::',
                '',
            ],
            'CALZADO' => [
                'Marca:',
                'Modelo:',
                'Material de Capellada (%): ',
                'Material de Forro (%):',
                'Material de Plantilla (%):',
                'Material de Suela (%):',
                'Talla:',
                'Colores:',
                'Incluye:',
                'Empaque (Granel o Cajas):',
                '',
            ],
            'ROPA' => [
                'Marca:',
                'Modelo:',
                'Material Exterior (%):',
                'Material del Forro (%):',
                'Material del Relleno (%):',
                'Material del Cierre (%):',
                'Material del Puños (%):',
                'Talla:',
                'Colores:',
                'Incluye:',
                '',
            ],
            'TECNOLOGIA' => [
                'Material:',
                'Marca:',
                'Modelo:',
                'Tamaño (Producto):',
                'Potencia:',
                'Voltaje:',
                'Amperaje:',
                'Bateria',
                'Peso Neto (Producto):',
                'Incluye:',
                'Unidad de medida:',
                'Función:',
                '',
            ],
            'TELA' => [
                'Material (%):',
                'Marca:',
                'Modelo:',
                'Tamaño (Producto):',
                'Gramaje (g/m²):',
                'Tipo de Tela:',
                'Cantidad de Rollos:',
                'Uso:',
                '',
            ],
            'AUTOMOTRIZ' => [
                'Material:',
                'Marca:',
                'Modelo:',
                'Tamaño (Producto):',
                'Compatibilidad (vehiculo/moto):',
                'Voltaje:',
                'Potencia:',
                'Peso Neto (Producto):',
                'Incluye:',
                'Unidad de medida:',
                'Función:',
                '',
            ],
            'MOVILIDAD PERSONAL' => [
                'Material:',
                'Marca:',
                'Modelo:',
                'Tamaño (Producto):',
                'Tamaño de ruedas:',
                'Distancia entre ruedas:',
                'Voltaje:',
                'Potencia:',
                'Amperaje:',
                'Autonomia:',
                'Velocidad maxima:',
                'Peso Neto (Producto):',
                'Capacidad de Carga:',
                'Tipo de Bateria:',
                'Incluye:',
            ],
            'MAQUINARIA' => [
                'Material:',
                'Marca:',
                'Modelo:',
                'Tamaño (Producto):',
                'Potencia:',
                'Voltaje:',
                'Amperaje:',
                'Peso Neto (Producto):',
                'Incluye:',
                'Funcion:',
                '',
                '',
            ],
        ];
    }

    private function restoreExcelDrawingExtentsFromTemplate(string $templatePath, string $generatedPath): void
    {
        if (!is_file($templatePath) || !is_file($generatedPath)) {
            return;
        }

        $tplZip = new ZipArchive();
        if ($tplZip->open($templatePath) !== true) {
            Log::warning('Excel confirmación: no se pudo abrir la plantilla para restaurar tamaños de dibujo');

            return;
        }

        $outZip = new ZipArchive();
        if ($outZip->open($generatedPath) !== true) {
            $tplZip->close();
            Log::warning('Excel confirmación: no se pudo abrir el archivo generado para restaurar tamaños de dibujo');

            return;
        }

        $tplNames = $this->listXlsxDrawingXmlPartNames($tplZip);
        $outNames = $this->listXlsxDrawingXmlPartNames($outZip);

        $count = min(count($tplNames), count($outNames));
        for ($i = 0; $i < $count; $i++) {
            $tplXml = $tplZip->getFromName($tplNames[$i]);
            $outXml = $outZip->getFromName($outNames[$i]);
            if ($tplXml === false || $outXml === false) {
                continue;
            }
            $patched = $this->applyTemplateDrawingExtSizesOntoOutputXml($tplXml, $outXml);
            $patched = $this->applyTemplateDrawingSrcRectsOntoOutputXml($tplXml, $patched);
            $outZip->deleteName($outNames[$i]);
            $outZip->addFromString($outNames[$i], $patched);
        }

        $outZip->close();
        $tplZip->close();
    }

    /**
     * @return string[]
     */
    private function listXlsxDrawingXmlPartNames(ZipArchive $zip): array
    {
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && preg_match('#^xl/drawings/drawing\\d+\\.xml$#', $name)) {
                $names[] = $name;
            }
        }
        usort($names, 'strnatcasecmp');

        return $names;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function extractCxCyPairsFromSprmExtNodes(string $drawingXml): array
    {
        $dom = new \DOMDocument();
        if (@$dom->loadXML($drawingXml) === false) {
            return [];
        }
        $xp = new \DOMXPath($dom);
        $pairs = [];
        foreach ($xp->query('//*[local-name()="ext" and @cx and @cy]') as $node) {
            if (!($node instanceof \DOMElement)) {
                continue;
            }
            $pairs[] = [$node->getAttribute('cx'), $node->getAttribute('cy')];
        }

        return $pairs;
    }

    private function applyTemplateDrawingExtSizesOntoOutputXml(string $templateDrawingXml, string $outputDrawingXml): string
    {
        $sizes = $this->extractCxCyPairsFromSprmExtNodes($templateDrawingXml);
        if ($sizes === []) {
            return $outputDrawingXml;
        }

        $dom = new \DOMDocument();
        if (@$dom->loadXML($outputDrawingXml) === false) {
            return $outputDrawingXml;
        }
        $xp = new \DOMXPath($dom);
        $nodes = $xp->query('//*[local-name()="ext" and @cx and @cy]');
        if ($nodes === false) {
            return $outputDrawingXml;
        }

        // Solo restaurar tamaños del logo/plantilla; no tocar fotos de producto.
        $k = 0;
        foreach ($nodes as $node) {
            if (!($node instanceof \DOMElement)) {
                continue;
            }
            if ($this->drawingNodeBelongsToProductPhoto($node)) {
                continue;
            }
            if (!isset($sizes[$k])) {
                break;
            }
            $node->setAttribute('cx', $sizes[$k][0]);
            $node->setAttribute('cy', $sizes[$k][1]);
            $k++;
        }

        return $dom->saveXML();
    }

    private function applyTemplateDrawingSrcRectsOntoOutputXml(string $templateDrawingXml, string $outputDrawingXml): string
    {
        $tplDom = new \DOMDocument();
        if (@$tplDom->loadXML($templateDrawingXml) === false) {
            return $outputDrawingXml;
        }
        $outDom = new \DOMDocument();
        if (@$outDom->loadXML($outputDrawingXml) === false) {
            return $outputDrawingXml;
        }

        $tplXp = new \DOMXPath($tplDom);
        $outXp = new \DOMXPath($outDom);

        $tplSrcRects = $tplXp->query('//*[local-name()="srcRect"]');
        $outSrcRects = $outXp->query('//*[local-name()="srcRect"]');
        // Quitar recortes heredados en fotos de producto y no aplicar srcRect de plantilla sobre ellas.
        if ($outSrcRects !== false) {
            $toRemove = [];
            foreach ($outSrcRects as $o) {
                if ($o instanceof \DOMElement && $this->drawingNodeBelongsToProductPhoto($o)) {
                    $toRemove[] = $o;
                }
            }
            foreach ($toRemove as $o) {
                $o->parentNode?->removeChild($o);
            }
        }

        if ($tplSrcRects !== false && $outSrcRects !== false) {
            $outNonProduct = [];
            foreach ($outXp->query('//*[local-name()="srcRect"]') as $o) {
                if ($o instanceof \DOMElement && !$this->drawingNodeBelongsToProductPhoto($o)) {
                    $outNonProduct[] = $o;
                }
            }
            $n = min($tplSrcRects->length, count($outNonProduct));
            for ($i = 0; $i < $n; $i++) {
                $t = $tplSrcRects->item($i);
                $o = $outNonProduct[$i];
                if (!($t instanceof \DOMElement) || !($o instanceof \DOMElement)) {
                    continue;
                }
                foreach ($t->attributes as $attr) {
                    if (!($attr instanceof \DOMAttr)) {
                        continue;
                    }
                    $o->setAttribute($attr->name, $attr->value);
                }
            }
        }

        $tplBlips = $tplXp->query('//*[local-name()="blipFill"]');
        $outBlips = $outXp->query('//*[local-name()="blipFill"]');
        if ($tplBlips !== false && $outBlips !== false) {
            $outNonProductBlips = [];
            foreach ($outBlips as $outBfCandidate) {
                if ($outBfCandidate instanceof \DOMElement && !$this->drawingNodeBelongsToProductPhoto($outBfCandidate)) {
                    $outNonProductBlips[] = $outBfCandidate;
                }
            }
            $m = min($tplBlips->length, count($outNonProductBlips));
            for ($j = 0; $j < $m; $j++) {
                $tplBf = $tplBlips->item($j);
                $outBf = $outNonProductBlips[$j];
                if (!($tplBf instanceof \DOMElement) || !($outBf instanceof \DOMElement)) {
                    continue;
                }

                $tplSr = $tplXp->query('.//*[local-name()="srcRect"]', $tplBf)->item(0);
                $outSr = $outXp->query('.//*[local-name()="srcRect"]', $outBf)->item(0);

                if (($tplSr instanceof \DOMElement) && !($outSr instanceof \DOMElement)) {
                    $clone = $outDom->importNode($tplSr, true);
                    if (!($clone instanceof \DOMElement)) {
                        continue;
                    }
                    $anchorAfter = null;
                    foreach ($outBf->childNodes as $child) {
                        if (!($child instanceof \DOMElement)) {
                            continue;
                        }
                        if ($child->localName === 'blip') {
                            $anchorAfter = $child;

                            break;
                        }
                    }
                    if ($anchorAfter instanceof \DOMElement && $anchorAfter->nextSibling) {
                        $outBf->insertBefore($clone, $anchorAfter->nextSibling);
                    } elseif ($anchorAfter instanceof \DOMElement) {
                        $outBf->appendChild($clone);
                    } elseif ($outBf->firstChild) {
                        $outBf->insertBefore($clone, $outBf->firstChild);
                    } else {
                        $outBf->appendChild($clone);
                    }
                }
            }
        }

        return $outDom->saveXML();
    }

    private function normalizeCaracteristicaKey(string $key): string
    {
        return strtolower(trim(rtrim(trim($key), ':')));
    }

    private function isCampoFijoExcel(string $label): bool
    {
        $normalized = $this->normalizeCaracteristicaKey($label);

        foreach (self::CAMPOS_FIJOS_EXCEL as $campo) {
            if ($this->normalizeCaracteristicaKey($campo) === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $caracteristicas
     */
    private function resolveCaracteristicaValue(array $caracteristicas, string $label): string
    {
        if (array_key_exists($label, $caracteristicas)) {
            $direct = trim((string) $caracteristicas[$label]);
            if ($direct !== '') {
                return $direct;
            }
        }

        $normalizedLabel = $this->normalizeCaracteristicaKey($label);
        foreach ($caracteristicas as $key => $value) {
            if ($this->normalizeCaracteristicaKey((string) $key) === $normalizedLabel) {
                $found = trim((string) $value);
                if ($found !== '') {
                    return $found;
                }
            }
        }

        foreach ($this->caracteristicaLabelAliases($label) as $alias) {
            if (array_key_exists($alias, $caracteristicas)) {
                $found = trim((string) $caracteristicas[$alias]);
                if ($found !== '') {
                    return $found;
                }
            }
            $normalizedAlias = $this->normalizeCaracteristicaKey($alias);
            foreach ($caracteristicas as $key => $value) {
                if ($this->normalizeCaracteristicaKey((string) $key) === $normalizedAlias) {
                    $found = trim((string) $value);
                    if ($found !== '') {
                        return $found;
                    }
                }
            }
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private function caracteristicaLabelAliases(string $label): array
    {
        $normalized = $this->normalizeCaracteristicaKey($label);

        return match ($normalized) {
            'tamaño (producto)' => ['Tamaño:', 'Tamaño del producto:', 'Tamaño (Metros):'],
            'capacidad' => ['Capacidad (ml o kg):'],
            'peso neto (producto)' => ['Peso Neto:', 'Peso'],
            'unidad de medida' => ['Pares o Piezas:'],
            'unidad tamaño' => ['Unidad Tamaño'],
            'unidad capacidad' => ['Unidad Capacidad'],
            'unidad peso neto' => ['Unidad Peso Neto'],
            default => [],
        };
    }

    /**
     * Fusiona valor + unidad en el campo principal antes de exportar al Excel.
     *
     * @param  array<string, mixed>  $caracteristicas
     * @return array<string, mixed>
     */
    private function mergeUnidadesEnCaracteristicas(array $caracteristicas): array
    {
        $merged = $caracteristicas;

        $camposConUnidad = [
            [
                'valueKey' => 'Tamaño (Producto):',
                'unitKey' => 'Unidad Tamaño:',
                'legacyValueKeys' => ['Tamaño:', 'Tamaño del producto:', 'Tamaño (Metros):'],
            ],
            [
                'valueKey' => 'Capacidad:',
                'unitKey' => 'Unidad Capacidad:',
                'legacyValueKeys' => ['Capacidad (ml o kg):'],
            ],
            [
                'valueKey' => 'Peso Neto (Producto):',
                'unitKey' => 'Unidad Peso Neto:',
                'legacyValueKeys' => ['Peso Neto:', 'Peso'],
            ],
        ];

        foreach ($camposConUnidad as $campo) {
            $value = $this->resolveCaracteristicaValue($merged, $campo['valueKey']);
            if ($value === '') {
                foreach ($campo['legacyValueKeys'] as $legacyKey) {
                    $legacyValue = $this->resolveCaracteristicaValue($merged, $legacyKey);
                    if ($legacyValue !== '') {
                        $value = $legacyValue;
                        $merged[$campo['valueKey']] = $legacyValue;
                        break;
                    }
                }
            }

            $unit = $this->resolveCaracteristicaValue($merged, $campo['unitKey']);
            if ($value !== '' && $unit !== '') {
                $merged[$campo['valueKey']] = $this->concatenarValorConUnidad($value, $unit);
            } elseif ($value !== '') {
                $merged[$campo['valueKey']] = $value;
            }
        }

        unset(
            $merged['Unidad Tamaño:'],
            $merged['Unidad Capacidad:'],
            $merged['Unidad Peso Neto:']
        );

        return $merged;
    }

    private function concatenarValorConUnidad(string $value, string $unit): string
    {
        $value = trim($value);
        $unit = trim($unit);
        if ($value === '' || $unit === '') {
            return $value;
        }

        if (preg_match('/\b' . preg_quote($unit, '/') . '\b$/iu', $value)) {
            return $value;
        }

        return trim($value . ' ' . $unit);
    }

    private function isUnidadCompanionLabel(string $label): bool
    {
        $normalized = $this->normalizeCaracteristicaKey($label);

        return in_array($normalized, ['unidad tamaño', 'unidad capacidad', 'unidad peso neto'], true);
    }

    /**
     * @param  array<string, mixed>  $caracteristicas
     */
    private function formatCaracteristicaCell(string $label, string $value, array $caracteristicas = []): string
    {
        if ($value === '') {
            return $label;
        }

        // Tras mergeUnidadesEnCaracteristicas el valor ya incluye la unidad; esto cubre datos legacy.
        $unitKey = $this->unidadKeyForLabel($label);
        if ($unitKey !== null) {
            $unit = $this->resolveCaracteristicaValue($caracteristicas, $unitKey);
            if ($unit !== '') {
                $value = $this->concatenarValorConUnidad($value, $unit);
            }
        }

        $labelBase = rtrim(trim($label), ':');

        return $labelBase . ': ' . $value;
    }

    private function unidadKeyForLabel(string $label): ?string
    {
        $normalized = strtolower(rtrim(trim($label), ':'));

        return match ($normalized) {
            'tamaño (producto)', 'tamaño', 'tamaño del producto', 'tamaño (metros)' => 'Unidad Tamaño:',
            'capacidad', 'capacidad (ml o kg)' => 'Unidad Capacidad:',
            'peso neto (producto)', 'peso neto', 'peso' => 'Unidad Peso Neto:',
            default => null,
        };
    }

    private function removeConflictingMergedCellsBeforeExcelConfirmacionBlock(
        Worksheet $sheet,
        int $firstRow,
        int $lastRow,
        string $firstColLetter,
        string $lastColLetter
    ): void {
        $minCI = Coordinate::columnIndexFromString($firstColLetter);
        $maxCI = Coordinate::columnIndexFromString($lastColLetter);
        $eIdx = Coordinate::columnIndexFromString('E');

        $toRemove = [];
        foreach (array_keys($sheet->getMergeCells()) as $range) {
            $b = Coordinate::rangeBoundaries($range);
            $mergeMinCI = min($b[0][0], $b[1][0]);
            $mergeMaxCI = max($b[0][0], $b[1][0]);
            $mergeMinRow = min($b[0][1], $b[1][1]);
            $mergeMaxRow = max($b[0][1], $b[1][1]);

            if ($mergeMinCI === $eIdx && $mergeMaxCI === $eIdx) {
                continue;
            }

            $colsIntersect = $mergeMinCI <= $maxCI && $mergeMaxCI >= $minCI;
            $rowsIntersect = $mergeMinRow <= $lastRow && $mergeMaxRow >= $firstRow;

            if ($colsIntersect && $rowsIntersect) {
                $toRemove[] = $range;
            }
        }

        foreach ($toRemove as $range) {
            try {
                $sheet->unmergeCells($range);
            } catch (\Throwable $e) {
                Log::warning('Excel confirmación: no se pudo descombinar ' . $range . ' — ' . $e->getMessage());
            }
        }
    }

    /**
     * Inserta la foto como imagen embebida en la celda (no como texto base64).
     */
    private function embedFotoEnCelda(Worksheet $sheet, string $coordinate, string $foto, int $rowsNeeded): bool
    {
        $binary = $this->resolveFotoBinary($foto);
        if ($binary === null) {
            $sheet->setCellValueExplicit($coordinate, '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            return false;
        }

        $imageResource = @imagecreatefromstring($binary);
        if ($imageResource === false) {
            Log::warning('Excel confirmación: no se pudo decodificar imagen de producto', [
                'coordinate' => $coordinate,
            ]);
            $sheet->setCellValueExplicit($coordinate, '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            return false;
        }

        // Forzamos PNG para preservar transparencia al reescalar.

        [$cellWidthPx, $cellHeightPx] = $this->estimateMergedCellSizePx($sheet, $coordinate, $rowsNeeded);
        $pad = 4;
            $maxW = max(1, min($cellWidthPx - ($pad * 2), 180));
            $maxH = max(1, min($cellHeightPx - ($pad * 2), 240));

            // Escalar manteniendo proporción; el cuadro del drawing = tamaño real de la foto.
            $srcW = max(1, imagesx($imageResource));
            $srcH = max(1, imagesy($imageResource));
            $scale = min($maxW / $srcW, $maxH / $srcH);
            $drawW = max(1, (int) round($srcW * $scale));
            $drawH = max(1, (int) round($srcH * $scale));

            $resized = imagecreatetruecolor($drawW, $drawH);
            if ($resized === false) {
                imagedestroy($imageResource);
                $sheet->setCellValueExplicit($coordinate, '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                return false;
            }

            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $drawW, $drawH, $transparent);
            imagealphablending($resized, true);
            imagecopyresampled($resized, $imageResource, 0, 0, 0, 0, $drawW, $drawH, $srcW, $srcH);
            imagedestroy($imageResource);

            $offsetX = $pad + (int) floor(($maxW - $drawW) / 2);
            $offsetY = $pad + (int) floor(($maxH - $drawH) / 2);

            $drawing = new MemoryDrawing();
            $drawing->setName('Foto producto');
            $drawing->setDescription('Foto producto');
            $drawing->setImageResource($resized);
            $drawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
            $drawing->setMimeType(MemoryDrawing::MIMETYPE_PNG);
            $drawing->setCoordinates($coordinate);
            $drawing->setResizeProportional(true);
            // setImageResource ya fijó width/height al tamaño proporcional del PNG
            $drawing->setOffsetX(max(0, $offsetX));
            $drawing->setOffsetY(max(0, $offsetY));
            $drawing->setWorksheet($sheet);

        $sheet->setCellValueExplicit($coordinate, '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        return true;
    }

    /**
     * Estima el tamaño en px de la celda D mergeada (alto = filas del bloque).
     *
     * @return array{0: int, 1: int}
     */
    private function estimateMergedCellSizePx(Worksheet $sheet, string $coordinate, int $rowsNeeded): array
    {
        [$col, $row] = Coordinate::coordinateFromString($coordinate);
        $startRow = (int) $row;

        $colWidth = $sheet->getColumnDimension($col)->getWidth();
        if ($colWidth < 0) {
            $colWidth = $sheet->getDefaultColumnDimension()->getWidth();
        }
        if ($colWidth < 0) {
            $colWidth = 14.0;
        }

        $font = $sheet->getParent()?->getDefaultStyle()->getFont() ?? new Font();
        $widthPx = (int) round(SharedDrawing::cellDimensionToPixels((float) $colWidth, $font));

        $heightPx = 0;
        for ($r = $startRow; $r < $startRow + max(1, $rowsNeeded); $r++) {
            $rh = $sheet->getRowDimension($r)->getRowHeight();
            if ($rh < 0) {
                $rh = $sheet->getDefaultRowDimension()->getRowHeight();
            }
            if ($rh < 0) {
                $rh = 15.0;
            }
            $heightPx += (int) round(SharedDrawing::pointsToPixels((float) $rh));
        }

        return [max(60, $widthPx), max(60, $heightPx)];
    }

    private function drawingNodeBelongsToProductPhoto(\DOMNode $node): bool
    {
        $current = $node;
        while ($current !== null) {
            if ($current instanceof \DOMElement) {
                $local = strtolower((string) $current->localName);
                // Nombre dentro del propio pic
                if ($local === 'pic' && $this->picElementIsProductPhoto($current)) {
                    return true;
                }
                // xdr:ext / a:ext son hermanos de xdr:pic dentro del anchor
                if (in_array($local, ['onecellanchor', 'twocellanchor', 'absoluteanchor'], true)) {
                    $owner = $current->ownerDocument;
                    if ($owner instanceof \DOMDocument) {
                        $xp = new \DOMXPath($owner);
                        foreach ($xp->query('.//*[local-name()="pic"]', $current) as $pic) {
                            if ($pic instanceof \DOMElement && $this->picElementIsProductPhoto($pic)) {
                                return true;
                            }
                        }
                    }
                }
            }
            $current = $current->parentNode;
        }

        return false;
    }

    private function picElementIsProductPhoto(\DOMElement $pic): bool
    {
        $owner = $pic->ownerDocument;
        if (!$owner instanceof \DOMDocument) {
            return false;
        }
        $xp = new \DOMXPath($owner);
        $nameAttr = $xp->query('.//*[local-name()="cNvPr"]/@name', $pic)->item(0);
        $name = (string) ($nameAttr?->nodeValue ?? '');

        return stripos($name, 'Foto producto') !== false;
    }

    private function resolveFotoBinary(string $foto): ?string
    {
        $foto = trim($foto);
        if ($foto === '') {
            return null;
        }

        if (preg_match('/^data:image\\/\\w+;base64,(.+)$/is', $foto, $matches)) {
            $decoded = base64_decode(str_replace(["\r", "\n", ' '], '', $matches[1]), true);

            return $decoded !== false && $this->isValidImageBinary($decoded) ? $decoded : null;
        }

        if (filter_var($foto, FILTER_VALIDATE_URL)) {
            $context = stream_context_create([
                'http' => ['timeout' => 15, 'follow_location' => 1],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $downloaded = @file_get_contents($foto, false, $context);

            return $downloaded !== false && $this->isValidImageBinary($downloaded) ? $downloaded : null;
        }

        $storagePath = $this->storageUploadPathFromDb($foto) ?? $this->objectStorage()->normalizeRelativePath($foto);
        if ($storagePath !== null && $this->objectStorage()->exists($storagePath)) {
            $stream = $this->objectStorage()->readStream($storagePath);
            if (is_resource($stream)) {
                $contents = stream_get_contents($stream);
                fclose($stream);
                if ($contents !== false && $this->isValidImageBinary($contents)) {
                    return $contents;
                }
            }
        }

        if (is_file($foto)) {
            $contents = @file_get_contents($foto);

            return $contents !== false && $this->isValidImageBinary($contents) ? $contents : null;
        }

        return null;
    }

    private function isValidImageBinary(string $binary): bool
    {
        return @getimagesizefromstring($binary) !== false;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function memoryDrawingFormatForMime(string $mime): array
    {
        return match (strtolower($mime)) {
            'image/png' => [MemoryDrawing::RENDERING_PNG, MemoryDrawing::MIMETYPE_PNG],
            'image/gif' => [MemoryDrawing::RENDERING_GIF, MemoryDrawing::MIMETYPE_GIF],
            default => [MemoryDrawing::RENDERING_JPEG, MemoryDrawing::MIMETYPE_JPEG],
        };
    }
}
