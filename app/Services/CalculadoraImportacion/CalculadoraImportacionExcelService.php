<?php

namespace App\Services\CalculadoraImportacion;

use App\Models\CalculadoraImportacion;
use App\Models\CalculadoraImportacionProveedor;
use App\Services\CalculadoraImportacionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CalculadoraImportacionExcelService
{
    private CalculadoraImportacionService $calculadoraImportacionService;

    public function __construct(CalculadoraImportacionService $calculadoraImportacionService)
    {
        $this->calculadoraImportacionService = $calculadoraImportacionService;
    }

    public function modificarExcelConFechas(CalculadoraImportacion $calculadora): void
    {
        try {
            $calculadora->load(['contenedor', 'proveedores.productos']);

            $contenedor = $calculadora->contenedor;
            if (!$contenedor) {
                Log::warning('No se encontró contenedor para la calculadora', ['calculadora_id' => $calculadora->id]);
                return;
            }

            $fechaCorte = $contenedor->f_cierre ? Carbon::parse($contenedor->f_cierre)->format('d/m/Y') : null;
            $fechaArribo = $contenedor->f_puerto ? Carbon::parse($contenedor->f_puerto)->format('d/m/Y') : null;
            if (!$fechaCorte || !$fechaArribo) {
                Log::warning('Fechas del contenedor no disponibles', [
                    'calculadora_id' => $calculadora->id,
                    'fecha_corte' => $fechaCorte,
                    'fecha_arribo' => $fechaArribo,
                ]);
                return;
            }

            $totalItems = 0;
            foreach ($calculadora->proveedores as $proveedor) {
                $totalItems += $proveedor->productos->count();
            }

            $fileUrl = $calculadora->url_cotizacion;
            $fileContents = $this->downloadFileFromUrl($fileUrl);

            if (!$fileContents) {
                Log::warning('Archivo Excel no encontrado, recreando desde calculadora', [
                    'calculadora_id' => $calculadora->id,
                    'url' => $fileUrl,
                ]);
                $result = $this->calculadoraImportacionService->regenerarExcelDesdeCalculadora($calculadora);
                if (!$result) {
                    Log::error('No se pudo recrear el archivo Excel', ['calculadora_id' => $calculadora->id]);
                    return;
                }
                $calculadora->refresh();
                $fileUrl = $calculadora->url_cotizacion;
                $fileContents = $this->downloadFileFromUrl($fileUrl);
                if (!$fileContents) {
                    Log::error('No se pudo descargar el archivo Excel tras recrearlo', [
                        'calculadora_id' => $calculadora->id,
                        'url' => $fileUrl,
                    ]);
                    return;
                }
            }

            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $extension = pathinfo($fileUrl, PATHINFO_EXTENSION) ?: 'xlsx';
            $tempFileName = uniqid('excel_modify_') . '.' . $extension;
            $tempFilePath = $tempPath . '/' . $tempFileName;
            file_put_contents($tempFilePath, $fileContents);

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempFilePath);
            $sheet = $spreadsheet->getActiveSheet();

            if (!empty($calculadora->cod_cotizacion)) {
                $sheet->setCellValue('D7', 'COTIZACION N° ' . $calculadora->cod_cotizacion);
            }
            Log::info('totalItems: ' . $totalItems);
            $filaServicioConsolidado = 37 + ($totalItems + 4);
            Log::info('filaServicioConsolidado: ' . $filaServicioConsolidado);
            $filaPagoImpuestos = 37 + ($totalItems + 5);
            Log::info('filaPagoImpuestos: ' . $filaPagoImpuestos);
            $sheet->setCellValue('P' . $filaServicioConsolidado, 'Servicio de Consolidado antes de la Fecha de Corte ' . $fechaCorte);
            $sheet->setCellValue('P' . $filaPagoImpuestos, 'Pago de Impuestos antes del Arribo ' . $fechaArribo);

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tempFilePath);

            $destinoPath = $this->getFilePathFromUrl($fileUrl);
            if ($destinoPath && file_exists(dirname($destinoPath))) {
                copy($tempFilePath, $destinoPath);
                Log::info('Excel modificado exitosamente', [
                    'calculadora_id' => $calculadora->id,
                    'fila_servicio' => $filaServicioConsolidado,
                    'fila_impuestos' => $filaPagoImpuestos,
                    'total_items' => $totalItems,
                ]);
            } else {
                Log::warning('No se pudo determinar la ruta de destino del archivo', [
                    'calculadora_id' => $calculadora->id,
                    'url' => $fileUrl,
                ]);
            }

            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
        } catch (\Exception $e) {
            Log::error('Error al modificar Excel con fechas: ' . $e->getMessage(), [
                'calculadora_id' => $calculadora->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function generarCodigosProveedorEnExcel(CalculadoraImportacion $calculadora): void
    {
        try {
            $calculadora->load(['contenedor']);
            $proveedores = $calculadora->proveedores()->orderBy('id')->get();

            if ($proveedores->isEmpty()) {
                Log::warning('No hay proveedores para generar códigos', ['calculadora_id' => $calculadora->id]);
                return;
            }

            $contenedor = $calculadora->contenedor;
            if (!$contenedor) {
                Log::warning('No se encontró contenedor para generar códigos', ['calculadora_id' => $calculadora->id]);
                return;
            }

            $carga = $contenedor->carga;
            $count = is_numeric($carga) ? str_pad($carga, 2, "0", STR_PAD_LEFT) : substr($carga, -2);

            $nameCliente = $calculadora->nombre_cliente;

            $fileUrl = $calculadora->url_cotizacion;
            $fileContents = $this->downloadFileFromUrl($fileUrl);

            if (!$fileContents) {
                Log::warning('Archivo Excel no encontrado para códigos, recreando', [
                    'calculadora_id' => $calculadora->id,
                    'url' => $fileUrl,
                ]);
                $result = $this->calculadoraImportacionService->regenerarExcelDesdeCalculadora($calculadora);
                if (!$result) {
                    Log::error('No se pudo recrear el archivo Excel para generar códigos', [
                        'calculadora_id' => $calculadora->id,
                    ]);
                    return;
                }
                $calculadora->refresh();
                $fileUrl = $calculadora->url_cotizacion;
                $fileContents = $this->downloadFileFromUrl($fileUrl);
                if (!$fileContents) {
                    Log::error('No se pudo descargar el archivo Excel tras recrearlo para generar códigos', [
                        'calculadora_id' => $calculadora->id,
                        'url' => $fileUrl,
                    ]);
                    return;
                }
            }

            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $extension = pathinfo($fileUrl, PATHINFO_EXTENSION) ?: 'xlsx';
            $tempFileName = uniqid('excel_codes_') . '.' . $extension;
            $tempFilePath = $tempPath . '/' . $tempFileName;
            file_put_contents($tempFilePath, $fileContents);

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempFilePath);
            $sheet2 = $spreadsheet->getSheet(1);

            $columnStart = "C";
            $columnTotales = "";
            $stop = false;
            while (!$stop) {
                $cell = $sheet2->getCell($columnStart . "3")->getValue();
                if (strtoupper(trim($cell)) == "TOTALES") {
                    $columnTotales = $columnStart;
                    $stop = true;
                } else {
                    $columnStart = $this->incrementColumn($columnStart);
                }
            }

            $rowCodeSupplier = 3;
            $rowProveedores = 4;
            $processedRanges = [];

            $base = $this->calculadoraImportacionService->codeSupplierBasePrefix((string) $nameCliente, $carga);
            $codesList = $proveedores
                ->pluck('code_supplier')
                ->map(fn ($c) => trim((string) ($c ?? '')))
                ->filter()
                ->values()
                ->all();
            $nextIdx = $this->calculadoraImportacionService->maxCodeSupplierSuffixForBase($base, $codesList) + 1;

            $proveedorIndex = 0;
            $columnStart = "C";
            $stop = false;

            while (!$stop && $proveedorIndex < $proveedores->count()) {
                if ($columnStart == $columnTotales) {
                    $stop = true;
                    break;
                }

                $cell = $sheet2->getCell($columnStart . $rowProveedores);
                $currentRange = $cell->getMergeRange();

                if ($currentRange && in_array($currentRange, $processedRanges)) {
                    $columnStart = $this->incrementColumn($columnStart);
                    continue;
                }

                if ($currentRange) {
                    $processedRanges[] = $currentRange;
                }

                $proveedorCalculadora = $proveedores[$proveedorIndex];
                $existingCode = trim((string) ($proveedorCalculadora->code_supplier ?? ''));
                if ($base !== '' && $existingCode !== '' && preg_match('/^' . preg_quote($base, '/') . '-\d+$/', $existingCode)) {
                    $codeSupplier = $existingCode;
                } else {
                    $codeSupplier = $this->generateCodeSupplier($nameCliente, $carga, $count, $nextIdx);
                    $nextIdx++;
                    $codesList[] = $codeSupplier;
                }

                $startCol = $columnStart;
                $endCol = $columnStart;
                if ($currentRange) {
                    $parts = explode(':', $currentRange);
                    if (count($parts) === 2) {
                        $startCol = preg_replace('/\d+/', '', $parts[0]);
                        $endCol = preg_replace('/\d+/', '', $parts[1]);
                    }
                }

                $sheet2->setCellValue($startCol . $rowCodeSupplier, $codeSupplier);
                if ($startCol != $endCol) {
                    $sheet2->mergeCells($startCol . $rowCodeSupplier . ':' . $endCol . $rowCodeSupplier);
                }

                $proveedorId = $proveedorCalculadora->id;
                CalculadoraImportacionProveedor::where('id', $proveedorId)->update(['code_supplier' => $codeSupplier]);

                $columnStart = $this->incrementColumn($endCol);
                $proveedorIndex++;
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tempFilePath);

            $destinoPath = $this->getFilePathFromUrl($fileUrl);
            if ($destinoPath && file_exists(dirname($destinoPath))) {
                copy($tempFilePath, $destinoPath);
                Log::info('Códigos de proveedor escritos en Excel exitosamente', [
                    'calculadora_id' => $calculadora->id,
                    'proveedores_procesados' => $proveedorIndex,
                ]);
            } else {
                Log::warning('No se pudo determinar la ruta de destino del archivo', [
                    'calculadora_id' => $calculadora->id,
                    'url' => $fileUrl,
                ]);
            }

            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
        } catch (\Exception $e) {
            Log::error('Error al generar códigos de proveedor en Excel: ' . $e->getMessage(), [
                'calculadora_id' => $calculadora->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function downloadFileFromUrl(?string $fileUrl): ?string
    {
        if (empty($fileUrl)) {
            return null;
        }

        try {
            if (filter_var($fileUrl, FILTER_VALIDATE_URL)) {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 60,
                        'method' => 'GET',
                        'header' => 'User-Agent: Mozilla/5.0',
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ]);

                $content = @file_get_contents($fileUrl, false, $context);
                if ($content !== false && strlen($content) > 0) {
                    return $content;
                }

                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $fileUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

                    $content = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($content !== false && $httpCode == 200 && strlen($content) > 0) {
                        return $content;
                    }
                }
            }

            if (strpos($fileUrl, '/storage/') !== false) {
                $path = preg_replace('#^.*/storage/#', '', $fileUrl);
                $storagePath = storage_path('app/public/' . $path);
                if (file_exists($storagePath)) {
                    return file_get_contents($storagePath);
                }
            }

            if (file_exists($fileUrl)) {
                return file_get_contents($fileUrl);
            }

            $publicPath = storage_path('app/public/' . ltrim($fileUrl, '/'));
            if (file_exists($publicPath)) {
                return file_get_contents($publicPath);
            }

            Log::error('No se pudo encontrar el archivo: ' . $fileUrl);
            return null;
        } catch (\Exception $e) {
            Log::error('Error al descargar archivo: ' . $e->getMessage(), ['url' => $fileUrl]);
            return null;
        }
    }

    public function getFilePathFromUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        try {
            if (strpos($url, 'http') === 0) {
                $parsedUrl = parse_url($url);
                $path = $parsedUrl['path'] ?? '';

                if (strpos($path, '/storage/') === 0) {
                    $path = substr($path, 9);
                }

                return storage_path('app/public/' . $path);
            }

            if (strpos($url, '/storage/') === 0) {
                $path = substr($url, 9);
                return storage_path('app/public/' . $path);
            }

            if (file_exists($url)) {
                return $url;
            }

            $publicPath = storage_path('app/public/' . ltrim($url, '/'));
            if (file_exists($publicPath)) {
                return $publicPath;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error al obtener ruta del archivo: ' . $e->getMessage(), ['url' => $url]);
            return null;
        }
    }

    public function generateCodeSupplier(string $string, $carga, $rowCount, int $index): string
    {
        if (is_numeric($carga)) {
            $carga = (string) (int) $carga;
        } elseif ($carga !== null && $carga !== '') {
            $carga = substr((string) $carga, -2);
        } else {
            $carga = '';
        }

        $words = explode(" ", trim($string));
        $code = "";

        foreach ($words as $word) {
            if (strlen($code) >= 4) break;
            if (strlen($word) >= 2) {
                $code .= strtoupper(substr($word, 0, 2));
            }
        }

        return $code . $carga . "-" . $index;
    }

    public function incrementColumn(string $column, int $increment = 1): string
    {
        $column = strtoupper($column);
        $length = strlen($column);
        $number = 0;

        for ($i = 0; $i < $length; $i++) {
            $number = $number * 26 + (ord($column[$i]) - ord('A') + 1);
        }

        $number += $increment;

        $result = '';
        while ($number > 0) {
            $number--;
            $result = chr(65 + ($number % 26)) . $result;
            $number = intval($number / 26);
        }

        return $result;
    }
}

