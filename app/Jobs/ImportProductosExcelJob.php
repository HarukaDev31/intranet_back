<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\BaseDatos\ProductoImportadoExcel;
use App\Models\ImportProducto;
use App\Events\ImportacionExcelCompleted;

class ImportProductosExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $idImportProducto;
    protected $extractPath;

    /**
     * Create a new job instance.
     */
    public function __construct($filePath, $idImportProducto)
    {
        $this->filePath = $filePath;
        $this->idImportProducto = $idImportProducto;
        Log::info('Constructor ImportProductosExcelJob - ID: ' . $idImportProducto);
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            Log::info('Iniciando Job de importación de productos - ID: ' . $this->idImportProducto);
            
            $importExists = ImportProducto::find($this->idImportProducto);
            if (!$importExists) {
                Log::error("ImportProducto con ID {$this->idImportProducto} no encontrado");
                return;
            }
            
            Log::info("ImportProducto verificado - ID: {$this->idImportProducto} existe");

            // Extraer el archivo Excel como ZIP para acceder a las imágenes
            $this->extractPath = storage_path('app/temp/excel_' . uniqid());
            $this->extractExcelImages($this->filePath, $this->extractPath);

            // Cargar el archivo Excel usando PhpSpreadsheet
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($this->filePath);
            $reader->setReadDataOnly(false);
            $reader->setIncludeCharts(false);

            $spreadsheet = $reader->load($this->filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            // Obtener celdas combinadas (merge) de la columna A
            $mergedCells = $sheet->getMergeCells();
            Log::info("Total de rangos mergeados encontrados: " . count($mergedCells));

            // Filtrar solo los rangos de la columna A
            $columnARanges = [];
            foreach ($mergedCells as $range) {
                if (strpos($range, 'A') !== false) {
                    $columnARanges[] = $range;
                    Log::info('Rango de columna A encontrado: ' . $range);
                }
            }
            
            Log::info('Total de rangos de columna A: ' . count($columnARanges));

            // Función para obtener todos los rangos únicos de la columna A
            $getUniqueRanges = function () use ($sheet, $highestRow, $columnARanges) {
                $ranges = [];
                $row = 3;
                
                while ($row <= $highestRow) {
                    $cellValue = $sheet->getCell("A$row")->getCalculatedValue();
                    
                    // Si la celda está vacía o es "-", terminar
                    if ($cellValue === null || $cellValue === "" || $cellValue === "-") {
                        Log::info("Celda A$row vacía o con '-', terminando procesamiento");
                        break;
                    }
                    
                    // Buscar el rango mergeado específico para esta fila
                    $startRow = $row;
                    $endRow = $row;
                    
                    foreach ($columnARanges as $range) {
                        list($start, $end) = explode(':', $range);
                        $rangeStartRow = (int)preg_replace('/[A-Z]/', '', $start);
                        $rangeEndRow = (int)preg_replace('/[A-Z]/', '', $end);
                        
                        if ($row >= $rangeStartRow && $row <= $rangeEndRow) {
                            $startRow = $rangeStartRow;
                            $endRow = $rangeEndRow;
                            break;
                        }
                    }
                    
                    // Solo agregar si no es un rango duplicado
                    $rangeKey = $startRow . '-' . $endRow;
                    if (!isset($ranges[$rangeKey])) {
                        $ranges[$rangeKey] = [
                            'start' => $startRow,
                            'end' => $endRow,
                            'item' => $cellValue
                        ];
                        Log::info('Rango encontrado: ' . $startRow . ' - ' . $endRow . ' Item: ' . $cellValue);
                    }
                    
                    // Saltar al siguiente rango
                    $row = $endRow + 1;
                }
                
                return array_values($ranges);
            };

            // Obtener todos los rangos únicos
            $uniqueRanges = $getUniqueRanges();
            $totalRows = count($uniqueRanges);
            
            Log::info('Total de rangos únicos encontrados: ' . $totalRows);
            
            $estadisticas = [
                'total_productos' => $totalRows,
                'productos_importados' => 0,
                'errores' => 0
            ];

            // Actualizar estadísticas en imports_productos
            $importProducto = ImportProducto::find($this->idImportProducto);
            if ($importProducto) {
                $importProducto->update([
                    'cantidad_rows' => $totalRows,
                    'estadisticas' => $estadisticas
                ]);
            }

            // Determinar el id del contenedor asociado a esta importación.
            // El campo 'id_contenedor_consolidado_documentacion_files' puede contener
            // - el id del DocumentacionFile (en cuyo caso debemos obtener su id_contenedor)
            // - o directamente el id del contenedor. Hacemos ambas comprobaciones.
            $contenedorId = null;
            try {
                if ($importProducto && $importProducto->id_contenedor_consolidado_documentacion_files) {
                    $linked = $importProducto->id_contenedor_consolidado_documentacion_files;
                    // Intentar obtener DocumentacionFile
                    $docFile = \App\Models\CargaConsolidada\DocumentacionFile::find($linked);
                    if ($docFile && $docFile->id_contenedor) {
                        $contenedorId = (int) $docFile->id_contenedor;
                        Log::info('ImportProductosExcelJob: contenedorId obtenido desde DocumentacionFile: ' . $contenedorId);
                    } else {
                        // Si no existe DocumentacionFile con ese id, interpretamos el valor como id_contenedor
                        $contenedorId = (int) $linked;
                        Log::info('ImportProductosExcelJob: contenedorId tomado directamente del campo linked: ' . $contenedorId);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('No se pudo determinar idContenedor desde ImportProducto: ' . $e->getMessage());
            }

            $importedCount = 0;
            $errors = [];

            // Obtener la colección de dibujos una sola vez para optimizar
            $drawingCollection = $sheet->getDrawingCollection();
            Log::info("Total de dibujos encontrados en la hoja: " . ($drawingCollection ? $drawingCollection->count() : 0));

            // Procesar cada rango único
            foreach ($uniqueRanges as $range) {
                $startRow = $range['start'];
                $endRow = $range['end'];
                $item = $range['item'];
                
                Log::info('Procesando rango: ' . $startRow . ' - ' . $endRow . ' Item: ' . $item);
                
                // Procesar solo la primera fila del rango (evitar duplicados)
                $i = $startRow;
                
                $nombre_comercial = $sheet->getCell("B$i")->getValue();
                $foto = $this->extractImageFromExcel($sheet, $startRow, $endRow, $this->extractPath, $drawingCollection);
                
                // Obtener características de todas las filas del rango
                $caracteristicas = "";
                for ($j2 = $startRow; $j2 <= $endRow; $j2++) {
                    $caracteristica = $sheet->getCell("D$j2")->getValue();
                    if ($caracteristica) {
                        $caracteristicas .= $caracteristica . " ";
                    }
                }
                $caracteristicas = trim($caracteristicas);
                
                $rubro = trim($sheet->getCell("E$i")->getValue());
                $tipo_producto = trim($sheet->getCell("F$i")->getValue());
                $precio_exw = $sheet->getCell("G$i")->getValue();
                $subpartida = $sheet->getCell("H$i")->getValue();
                $link = $sheet->getCell("I$i")->getValue();
                $unidad_comercial = $sheet->getCell("J$i")->getValue();
                $arancel_sunat = $sheet->getCell("K$i")->getValue();
                $arancel_tlc = $sheet->getCell("L$i")->getValue();
                $antidumping = $sheet->getCell("M$i")->getValue();
                $correlativo = $sheet->getCell("N$i")->getValue();
                $etiquetado = $sheet->getCell("O$i")->getValue();
                $doc_especial = $sheet->getCell("P$i")->getValue();
               
                // Guardar en la base de datos usando el modelo
                $productoData = [
                    'id_import_producto' => $this->idImportProducto,
                    'idContenedor' => $contenedorId,
                    'item' => $item,
                    'nombre_comercial' => $nombre_comercial,
                    'foto' => $foto,
                    'caracteristicas' => $caracteristicas,
                    'rubro' => $rubro,
                    'tipo_producto' => $tipo_producto,
                    'precio_exw' => $precio_exw,
                    'subpartida' => $subpartida,
                    'link' => $link,
                    'unidad_comercial' => $unidad_comercial,
                    'arancel_sunat' => $arancel_sunat,
                    'arancel_tlc' => $arancel_tlc,
                    'antidumping' => $antidumping,
                    'correlativo' => $correlativo,
                    'etiquetado' => $etiquetado,
                    'doc_especial' => $doc_especial
                ];

                try {
                    ProductoImportadoExcel::create($productoData);
                    $importedCount++;
                    $estadisticas['productos_importados']++;
                    Log::info('Producto importado exitosamente: ' . $item);
                } catch (\Exception $e) {
                    $estadisticas['errores']++;
                    $errorMsg = 'Error al insertar producto ' . $item . ': ' . $e->getMessage();
                    $errors[] = $errorMsg;
                    Log::error($errorMsg);
                }
            }

            // Actualizar estadísticas finales en imports_productos
            if ($importProducto) {
                $importProducto->update([
                    'estadisticas' => $estadisticas
                ]);
            }

            // Limpiar archivos temporales
            $this->deleteDirectory($this->extractPath);

            Log::info("Importación completada. Total productos insertados: $importedCount, Total rangos: $totalRows, Errores: " . count($errors));

            // Emitir evento de importación completada
            event(new ImportacionExcelCompleted(
                $importProducto,
                'completed',
                "Importación completada exitosamente. $importedCount productos importados de $totalRows totales.",
                $estadisticas
            ));
            //notify to Documentacion channel
        } catch (\Exception $e) {
            Log::error('Error en ImportProductosExcelJob: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Actualizar el estado del import como fallido
            $importProducto = ImportProducto::find($this->idImportProducto);
            if ($importProducto) {
                $importProducto->update([
                    'estadisticas' => [
                        'error' => $e->getMessage(),
                        'status' => 'failed'
                    ]
                ]);

                // Emitir evento de error en la importación
                event(new ImportacionExcelCompleted(
                    $importProducto,
                    'failed',
                    "Error en la importación: " . $e->getMessage(),
                    [
                        'error' => $e->getMessage(),
                        'status' => 'failed'
                    ]
                ));
            }
        }
    }

    /**
     * Extraer imágenes del archivo Excel
     */
    private function extractExcelImages($filePath, $extractPath)
    {
        try {
            Log::info('Extrayendo imágenes de: ' . $filePath);

            // Crear directorio de extracción
            if (!file_exists($extractPath)) {
                mkdir($extractPath, 0777, true);
            }

            // Renombrar el archivo temporal a .zip
            $zipPath = $extractPath . '/temp.zip';
            if (copy($filePath, $zipPath)) {
                $zip = new \ZipArchive;
                $res = $zip->open($zipPath);
                if ($res === TRUE) {
                    Log::info("Archivo ZIP abierto correctamente");
                    $zip->extractTo($extractPath);
                    $zip->close();
                    unlink($zipPath);
                    Log::info("Archivo Excel extraído exitosamente");

                    // Verificar si se extrajeron las imágenes
                    $mediaPath = $extractPath . '/xl/media/';
                    if (is_dir($mediaPath)) {
                        $files = scandir($mediaPath);
                        Log::info("Archivos encontrados en xl/media: " . (count($files) - 2));
                    } else {
                        Log::info("Directorio xl/media no encontrado - puede ser normal para archivos sin imágenes");
                    }
                } else {
                    Log::error("No se pudo abrir el archivo ZIP. Error: " . $res);
                    // Para archivos .xlsm, esto puede ser normal si no tienen imágenes
                }
            } else {
                Log::error("No se pudo copiar el archivo para extracción");
            }
        } catch (\Exception $e) {
            Log::error('Error al extraer imágenes: ' . $e->getMessage());
            // No lanzar excepción, continuar sin imágenes
        }
    }

    /**
     * Extraer imagen específica del Excel
     */
    private function extractImageFromExcel($sheet, $startRow, $endRow, $extractPath, $drawingCollection = null)
    {
        $foto = '';

        try {
            // Usar la colección de dibujos pasada como parámetro o obtenerla si no se proporciona
            if ($drawingCollection === null) {
                $drawingCollection = $sheet->getDrawingCollection();
            }
            
            if (!$drawingCollection || $drawingCollection->count() === 0) {
                Log::info("No se encontraron dibujos en la hoja para el rango C{$startRow}-C{$endRow}");
                return $foto;
            }

            // Crear un mapa de coordenadas para búsqueda más eficiente
            $drawingMap = [];
            foreach ($drawingCollection as $drawing) {
                $coordinates = $drawing->getCoordinates();
                $drawingMap[$coordinates] = $drawing;
            }

            // Buscar la imagen en todas las filas del rango mergeado
            for ($searchRow = $startRow; $searchRow <= $endRow; $searchRow++) {
                $coordinate = "C$searchRow";
                Log::info("Buscando imagen en fila $coordinate");
                
                // Verificar si existe un dibujo en esta coordenada específica
                if (isset($drawingMap[$coordinate])) {
                    $drawing = $drawingMap[$coordinate];
                    $drawingPath = $drawing->getPath();
                    Log::info("Encontrado dibujo en $coordinate: " . $drawingPath);

                    $hashPosition = strpos($drawingPath, '#');
                    if ($hashPosition !== false) {
                        $extractedPart = substr($drawingPath, $hashPosition + 1);
                        Log::info("Parte extraída: " . $extractedPart);

                        // Definir posibles ubicaciones de la imagen
                        $possiblePaths = [
                            $extractPath . '/xl/media/' . $extractedPart,
                            $extractPath . '/' . $extractedPart,
                            $extractPath . '/media/' . $extractedPart
                        ];

                        foreach ($possiblePaths as $imagePath) {
                            if (file_exists($imagePath)) {
                                $imageData = file_get_contents($imagePath);
                                if ($imageData !== false) {
                                    // Crear directorio si no existe
                                    $path = storage_path('app/public/productos/');
                                    if (!is_dir($path)) {
                                        mkdir($path, 0777, true);
                                    }

                                    // Obtener extensión original o usar jpg por defecto
                                    $extension = pathinfo($extractedPart, PATHINFO_EXTENSION);
                                    $extension = $extension ? $extension : 'jpg';

                                    $filename = 'productos/' . uniqid() . '.' . $extension;
                                    $fullPath = storage_path('app/public/' . $filename);

                                    if (file_put_contents($fullPath, $imageData)) {
                                        $foto = $filename;
                                        Log::info("Imagen guardada desde fila $coordinate: " . $foto);
                                        return $foto;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            Log::info("No se encontró imagen en el rango C{$startRow}-C{$endRow}");
            return $foto;
            
        } catch (\Exception $e) {
            Log::error("Error al extraer imagen: " . $e->getMessage());
            return $foto;
        }
    }

    /**
     * Eliminar directorio recursivamente
     */
    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
    public function failed(\Throwable $exception)
    {
        Log::error('Job ImportProductosExcelJob falló completamente', [
            'idImportProducto' => $this->idImportProducto,
            'filePath' => $this->filePath,
            'error' => $exception->getMessage()
        ]);
    }
}
