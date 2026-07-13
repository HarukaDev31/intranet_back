<?php

namespace App\Services\CargaConsolidada\Clientes;

use App\Models\CargaConsolidada\CotizacionProveedor;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ZipArchive;

class ExcelConfirmacionDocumentosService
{
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

            $caracteristicas = is_array($item['caracteristicas'] ?? null) ? $item['caracteristicas'] : [];
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
                $sheet->setCellValueExplicit('D' . $startRow, $foto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
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
                if ($label === '' || $this->isCampoFijoExcel($label)) {
                    continue;
                }

                $value = $this->resolveCaracteristicaValue($caracteristicas, $label);
                $cellValue = $this->formatCaracteristicaCell($label, $value);
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
                'Tamaño:',
                'Capacidad (ml o kg):',
                'Peso Neto:',
                'Incluye:',
                'Pares o Piezas:',
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
                'Tamaño del producto:',
                'Potencia:',
                'Voltaje:',
                'Amperaje:',
                'Bateria',
                'Peso Neto:',
                'Incluye:',
                'Pares o Piezas:',
                'Función:',
                '',
            ],
            'TELA' => [
                'Material (%):',
                'Marca:',
                'Modelo:',
                'Tamaño (Metros):',
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
                'Tamaño:',
                'Compatibilidad (vehiculo/moto):',
                'Voltaje:',
                'Potencia:',
                'Peso Neto:',
                'Incluye:',
                'Pares o Piezas:',
                'Función:',
                '',
            ],
            'MOVILIDAD PERSONAL' => [
                'Material:',
                'Marca:',
                'Modelo:',
                'Tamaño del producto:',
                'Tamaño de ruedas:',
                'Distancia entre ruedas:',
                'Voltaje:',
                'Potencia:',
                'Amperaje:',
                'Autonomia:',
                'Velocidad maxima:',
                'Peso Neto:',
                'Capacidad de Carga:',
                'Tipo de Bateria:',
                'Incluye:',
            ],
            'MAQUINARIA' => [
                'Material:',
                'Marca:',
                'Modelo:',
                'Tamaño:',
                'Potencia:',
                'Voltaje:',
                'Amperaje:',
                'Peso',
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

        $k = 0;
        foreach ($nodes as $node) {
            if (!($node instanceof \DOMElement)) {
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
        if ($tplSrcRects !== false && $outSrcRects !== false) {
            $n = min($tplSrcRects->length, $outSrcRects->length);
            for ($i = 0; $i < $n; $i++) {
                $t = $tplSrcRects->item($i);
                $o = $outSrcRects->item($i);
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
            $m = min($tplBlips->length, $outBlips->length);
            for ($j = 0; $j < $m; $j++) {
                $tplBf = $tplBlips->item($j);
                $outBf = $outBlips->item($j);
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
            return trim((string) $caracteristicas[$label]);
        }

        $normalizedLabel = $this->normalizeCaracteristicaKey($label);
        foreach ($caracteristicas as $key => $value) {
            if ($this->normalizeCaracteristicaKey((string) $key) === $normalizedLabel) {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function formatCaracteristicaCell(string $label, string $value): string
    {
        if ($value === '') {
            return $label;
        }

        $labelBase = rtrim(trim($label), ':');

        return $labelBase . ': ' . $value;
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
}
