<?php

namespace App\Http\Controllers\BaseDatos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\BaseDatos\ProductoImportadoExcel;
use App\Exports\ProductosExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\ImportProducto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductosController extends Controller
{
    /**
     * Obtener lista de productos
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);

            // Usar query builder con join explícito a contenedor según idContenedor
            $query = ProductoImportadoExcel::leftJoin('carga_consolidada_contenedor', 'productos_importados_excel.idContenedor', '=', 'carga_consolidada_contenedor.id')
                ->select('productos_importados_excel.*', 'carga_consolidada_contenedor.carga as carga_contenedor');

            // Aplicar filtros si están presentes
            if ($request->has('search') && $request->search) {
                $query->where('productos_importados_excel.nombre_comercial', 'like', '%' . $request->search . '%')
                    ->orWhere('productos_importados_excel.caracteristicas', 'like', '%' . $request->search . '%');
            }

            // Filtrar por idContenedor específico si se proporciona
            if ($request->has('campana') && $request->campana) {
                $query->where('carga', $request->campana);
            }

            $data = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $data->items(),
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ],
                'headers' => [
                    'total_productos' => [
                        'value' => $data->total(),
                        'label' => 'Total Productos'
                    ],

                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener productos: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener productos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener opciones de filtro para productos
     */
    public function filterOptions()
    {
        try {
            // Obtener cargas únicas de la tabla carga_consolidada_contenedor
            $cargas = Contenedor::getCargasUnicas();

            // Obtener otras opciones de filtro de productos
            $rubros = ProductoImportadoExcel::select('rubro')
                ->whereNotNull('rubro')
                ->where('rubro', '!=', '')
                ->distinct()
                ->orderBy('rubro')
                ->pluck('rubro')
                ->toArray();

            $tiposProducto = ProductoImportadoExcel::select('tipo_producto')
                ->whereNotNull('tipo_producto')
                ->where('tipo_producto', '!=', '')
                ->distinct()
                ->orderBy('tipo_producto')
                ->pluck('tipo_producto')
                ->toArray();

            $campanas = Contenedor::select('carga')
                ->whereNotNull('carga')
                ->where('carga', '!=', '')
                ->distinct()
                ->orderByRaw('CAST(carga AS UNSIGNED)')
                ->pluck('carga')
                ->toArray();


            return response()->json([
                'status' => 'success',
                'data' => [
                    'cargas' => $cargas,
                    'rubros' => $rubros,
                    'tipos_producto' => $tiposProducto,
                    'campanas' => $campanas,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener opciones de filtro: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener opciones de filtro: ' . $e->getMessage()
            ], 500);
        }
    }
    public function importExcel(Request $request)
    {
        try {
            Log::info('Iniciando importación de productos');

            $file = $request->file('excel_file');

            if (!$file) {
                Log::error('No se proporcionó archivo');
                return response()->json([
                    'success' => false,
                    'message' => 'No se proporcionó archivo Excel'
                ], 400);
            }

            // Validar tipo de archivo
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedExtensions = ['xlsx', 'xls', 'xlsm'];

            if (!in_array($extension, $allowedExtensions)) {
                Log::error('Tipo de archivo no permitido: ' . $extension);
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de archivo no permitido. Formatos válidos: ' . implode(', ', $allowedExtensions)
                ], 400);
            }

            Log::info('Archivo recibido: ' . $file->getClientOriginalName() . ' (tipo: ' . $extension . ')');

            // Crear registro de importación
            $importProducto = ImportProducto::create([
                'nombre_archivo' => $file->getClientOriginalName(),
                'ruta_archivo' => $file->store('imports/productos'),
                'cantidad_rows' => 0,
                'estadisticas' => []
            ]);

            Log::info('ImportProducto creado con ID: ' . $importProducto->id);

            // Guardar archivo temporalmente
            $tempPath = $file->store('temp');
            $fullTempPath = storage_path('app/' . $tempPath);

            // Importar productos usando el método privado
            $result = $this->importarProductosDesdeExcel($fullTempPath, $importProducto->id);

            // Actualizar estadísticas de la importación
            $importedCount = $result['success'] ? $result['data']['imported_count'] : 0;
            $importProducto->update([
                'cantidad_rows' => $importedCount,
                'estadisticas' => [
                    'imported_count' => $importedCount,
                    'total_rows_processed' => $result['success'] ? $result['data']['total_rows_processed'] ?? 0 : 0,
                    'errors' => $result['success'] ? $result['data']['errors'] : [],
                    'total_errors' => $result['success'] ? count($result['data']['errors']) : 0
                ]
            ]);

            Log::info("ImportProducto actualizado - Productos insertados: $importedCount");

            // Limpiar archivo temporal
            \Storage::delete($tempPath);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Productos importados correctamente',
                    'data' => array_merge($result['data'], [
                        'import_id' => $importProducto->id,
                        'import_info' => $importProducto
                    ])
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en importExcel: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Importar productos desde archivo Excel
     */
    private function importarProductosDesdeExcel($filePath, $idImportProducto)
    {
        try {
            Log::info('Cargando archivo Excel: ' . $filePath);

            // Extraer el archivo Excel como ZIP para acceder a las imágenes
            $extractPath = storage_path('app/temp/excel_' . uniqid());
            $this->extractExcelImages($filePath, $extractPath);

            // Cargar el archivo Excel usando PhpSpreadsheet con configuración específica para .xlsm
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(false); // Cambiar a false para leer información de merge
            $reader->setIncludeCharts(false); // No incluir gráficos

            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            // Obtener celdas combinadas (merge)
            $mergedCells = $sheet->getMergeCells();
            Log::info("Total de rangos mergeados encontrados: " . count($mergedCells));

            // Debug: mostrar todos los rangos mergeados
            foreach ($mergedCells as $range) {
                Log::info("Rango mergeado: $range");
            }

            // Alternativa: intentar obtener merge cells de otra manera
            if (count($mergedCells) == 0) {
                Log::info("Intentando método alternativo para obtener merge cells...");
                $mergedCells = $sheet->getMergeCells();
                Log::info("Método alternativo - Total de rangos mergeados: " . count($mergedCells));
            }

            // Función para obtener rango mergeado (simplificada como en el código original)
            $getMergeRange = function ($row) use ($mergedCells) {
                foreach ($mergedCells as $range) {
                    list($start, $end) = explode(':', $range);
                    $startRow = (int)preg_replace('/[A-Z]/', '', $start);
                    $endRow = (int)preg_replace('/[A-Z]/', '', $end);
                    if ($row >= $startRow && $row <= $endRow) {
                        return [$startRow, $endRow];
                    }
                }
                return [$row, $row];
            };

            $row = 3;
            $processedItems = [];
            $importedCount = 0;
            $totalRowsProcessed = 0;
            $errors = [];

            // Obtener la colección de dibujos (imágenes) del Excel
            $drawings = $sheet->getDrawingCollection();
            Log::info("Total de dibujos encontrados: " . count($drawings));
            Log::info("Procesando desde fila 3 hasta fila: $highestRow");

            while ($row <= $highestRow) {
                // Detectar rango mergeado en la columna 1 (A)
                list($startRow, $endRow) = $getMergeRange($row);
                Log::info('Procesando rango: ' . $startRow . ' - ' . $endRow);

                // Verificar si hay contenido en la celda A (como en el código original)
                $cellValue = $sheet->getCell("A$startRow")->getValue();
                Log::info("Valor de celda A$startRow: '" . $cellValue . "'");
                if ($cellValue == null || $cellValue == "" || $cellValue == "-") {
                    Log::info("Celda A$startRow vacía o con '-', deteniendo procesamiento");
                    break;
                }

                $item = trim($cellValue);
                Log::info('Procesando Item: ' . $item . ' en rango ' . $startRow . '-' . $endRow);

                // Evitar procesar el mismo item múltiples veces
                if (in_array($item, $processedItems)) {
                    Log::info('Item ya procesado, saltando: ' . $item);
                    $row = $endRow + 1;
                    continue;
                }

                $processedItems[] = $item;
                $totalRowsProcessed++;

                // Procesar solo una vez por rango mergeado
                $nombre_comercial = trim($sheet->getCell("B$startRow")->getValue());

                // Obtener imagen - buscar en todo el rango mergeado (como en el código original)
                $foto = '';
                Log::info("Buscando imagen en rango C$startRow a C$endRow. Total de dibujos: " . count($drawings));

                $imageFound = false;
                for ($searchRow = $startRow; $searchRow <= $endRow && !$imageFound; $searchRow++) {
                    Log::info("Buscando imagen en celda C$searchRow");
                    foreach ($drawings as $drawing) {
                        $coordinates = $drawing->getCoordinates();

                        if ($coordinates == "C$searchRow") {
                            $drawingPath = $drawing->getPath();
                            Log::info("Ruta del dibujo: " . $drawingPath);

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
                                                Log::info("Imagen guardada: " . $foto);
                                                $imageFound = true;
                                                break 2; // Salir de ambos foreach
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Si no se encontró imagen, intentar obtener valor de texto
                if (empty($foto)) {
                    $foto_valor = $sheet->getCell("C$startRow")->getValue();
                    $foto = !empty($foto_valor) ? trim($foto_valor) : '';
                    Log::info("No se encontró imagen, usando valor de texto: " . $foto_valor);
                }

                // Obtener características combinando todas las celdas del rango mergeado
                $caracteristicas = "";
                for ($j = $startRow; $j <= $endRow; $j++) {
                    $cellContent = trim($sheet->getCell("D$j")->getValue());
                    if (!empty($cellContent)) {
                        $caracteristicas .= $cellContent . " ";
                    }
                }
                $caracteristicas = trim($caracteristicas);

                // Obtener el resto de datos (usar la primera fila del rango)
                $rubro = trim($sheet->getCell("E$startRow")->getValue());
                $tipo_producto = trim($sheet->getCell("F$startRow")->getValue());
                $precio_exw = $sheet->getCell("G$startRow")->getValue();
                $subpartida = $sheet->getCell("H$startRow")->getValue();
                $link = $sheet->getCell("I$startRow")->getValue();
                $unidad_comercial = $sheet->getCell("J$startRow")->getValue();
                $arancel_sunat = $sheet->getCell("K$startRow")->getValue();
                $arancel_tlc = $sheet->getCell("L$startRow")->getValue();
                $antidumping = $sheet->getCell("M$startRow")->getValue();
                $correlativo = $sheet->getCell("N$startRow")->getValue();
                $etiquetado = $sheet->getCell("O$startRow")->getValue();
                $doc_especial = $sheet->getCell("P$startRow")->getValue();

                $data = [
                    'id_import_producto' => $idImportProducto,
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

                // Guardar en la base de datos usando el modelo
                try {
                    ProductoImportadoExcel::create($data);
                    $importedCount++;
                    Log::info('Producto insertado correctamente: ' . $item);
                } catch (\Exception $e) {
                    $errors[] = 'Error al insertar producto ' . $item . ': ' . $e->getMessage();
                    Log::error('Error al insertar producto: ' . $item . ' - ' . $e->getMessage());
                }

                // Avanzar al siguiente rango
                $row = $endRow + 1;
                Log::info("Avanzando a fila: $row");
            }

            // Limpiar archivos temporales
            $this->deleteDirectory($extractPath);

            Log::info("Importación completada. Total productos insertados: $importedCount, Filas procesadas: $totalRowsProcessed, Errores: " . count($errors));

            return [
                'success' => true,
                'data' => [
                    'imported_count' => $importedCount,
                    'total_rows_processed' => $totalRowsProcessed,
                    'errors' => $errors
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error en importarProductosDesdeExcel: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error en importarProductosDesdeExcel: ' . $e->getMessage()
            ];
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
    private function extractImageFromExcel($drawings, $startRow, $endRow, $extractPath)
    {
        $foto = '';

        // Optimización: buscar solo en la fila específica, no en todo el rango
        $searchRow = $startRow;

        foreach ($drawings as $drawing) {
            $coordinates = $drawing->getCoordinates();

            if ($coordinates == "C$searchRow") {
                $drawingPath = $drawing->getPath();
                Log::info("Encontrado dibujo en C$searchRow: " . $drawingPath);

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
                                    Log::info("Imagen guardada: " . $foto);
                                    return $foto;
                                }
                            }
                        }
                    }
                }
                break; // Salir del foreach una vez que encontramos el dibujo
            }
        }

        Log::info("No se encontró imagen para la fila C$searchRow");
        return $foto;
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
    /**
     * Exportar productos a Excel
     */
    public function export(Request $request)
    {
        try {
            // Obtener filtros
            $filters = [
                'tipoProducto' => $request->get('tipoProducto'),
                'campana' => $request->get('campana'),
                'rubro' => $request->get('rubro'),
            ];

            // Obtener formato de exportación y validar
            $format = strtolower($request->get('format', 'xlsx'));

            // Validar formatos soportados
            $supportedFormats = ['xlsx', 'xls', 'csv'];
            if (!in_array($format, $supportedFormats)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Formato no soportado. Formatos válidos: ' . implode(', ', $supportedFormats)
                ], 400);
            }

            // Generar nombre del archivo con extensión correcta
            $filename = 'productos_' . date('Y-m-d_H-i-s') . '.' . $format;

            // Exportar usando la librería Excel con formato explícito
            return Excel::download(new ProductosExport($filters), $filename, \Maatwebsite\Excel\Excel::XLSX);
        } catch (\Exception $e) {
            Log::error('Error al exportar productos: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al exportar productos: ' . $e->getMessage()
            ], 500);
        }
    }
    public function show($id)
    {
        try {
            $producto = ProductoImportadoExcel::find($id);
            if (!$producto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'data' => $producto
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener el producto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el producto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar producto
     */
    public function update(Request $request, $id)
    {
        try {
            $producto = ProductoImportadoExcel::find($id);
            if (!$producto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado'
                ], 404);
            }

            // Validar campos condicionales
            $errors = $this->validateConditionalFields($request);
            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Errores de validación',
                    'data' => $errors
                ], 422);
            }

            // Preparar datos para actualización
            $updateData = $this->prepareUpdateData($request);

            // Actualizar producto
            $producto->update($updateData);

            // Cargar relaciones para la respuesta
            $producto->load(['entidad', 'tipoEtiquetado']);

            return response()->json([
                'success' => true,
                'data' => $producto,
                'message' => 'Producto actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar producto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar producto: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Validar campos condicionales
     */
    private function validateConditionalFields(Request $request): array
    {
        $errors = [];

        // Validar antidumping_value solo si antidumping = "SI"
        if ($request->has('antidumping') && $request->antidumping === 'SI') {
            if (!$request->has('antidumping_value') || empty($request->antidumping_value)) {
                $errors['antidumping_value'] = 'El valor de antidumping es requerido cuando antidumping es "SI"';
            }
        }

        // Validar entidad_id solo si tipo_producto = "RESTRINGIDO"
        if ($request->has('tipo_producto') && $request->tipo_producto === 'RESTRINGIDO') {
            if (!$request->has('entidad_id') || empty($request->entidad_id)) {
                $errors['entidad_id'] = 'La entidad es requerida cuando el tipo de producto es "RESTRINGIDO"';
            }
        }

        // Validar tipo_etiquetado_id solo si etiquetado = "ESPECIAL"
        if ($request->has('etiquetado') && $request->etiquetado === 'ESPECIAL') {
            if (!$request->has('tipo_etiquetado_id') || empty($request->tipo_etiquetado_id)) {
                $errors['tipo_etiquetado_id'] = 'El tipo de etiquetado es requerido cuando etiquetado es "ESPECIAL"';
            }
        }

        // Validar observaciones solo si tiene_observaciones = true
        if ($request->has('tiene_observaciones') && $request->tiene_observaciones === true) {
            if (!$request->has('observaciones') || empty($request->observaciones)) {
                $errors['observaciones'] = 'Las observaciones son requeridas cuando tiene_observaciones es true';
            }
        }

        return $errors;
    }

    /**
     * Preparar datos para actualización
     */
    private function prepareUpdateData(Request $request): array
    {
        $data = [];

        // Campos básicos
        $basicFields = [
            'link',
            'arancel_sunat',
            'arancel_tlc',
            'correlativo',
            'antidumping',
            'tipo_producto',
            'etiquetado',
            'doc_especial'
        ];

        foreach ($basicFields as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->input($field);
            }
        }

        // Campos condicionales
        if ($request->has('antidumping') && $request->antidumping === 'SI' && $request->has('antidumping_value')) {
            $data['antidumping_value'] = $request->antidumping_value;
        } elseif ($request->has('antidumping') && $request->antidumping === 'NO') {
            $data['antidumping_value'] = null;
        }

        if ($request->has('tipo_producto') && $request->tipo_producto === 'RESTRINGIDO' && $request->has('entidad_id')) {
            $data['entidad_id'] = $request->entidad_id;
        } elseif ($request->has('tipo_producto') && $request->tipo_producto === 'LIBRE') {
            $data['entidad_id'] = null;
        }

        if ($request->has('etiquetado') && $request->etiquetado === 'ESPECIAL' && $request->has('tipo_etiquetado_id')) {
            $data['tipo_etiquetado_id'] = $request->tipo_etiquetado_id;
        } elseif ($request->has('etiquetado') && $request->etiquetado === 'NORMAL') {
            $data['tipo_etiquetado_id'] = null;
        }

        if ($request->has('tiene_observaciones')) {
            $data['tiene_observaciones'] = $request->tiene_observaciones;

            if ($request->tiene_observaciones === true && $request->has('observaciones')) {
                $data['observaciones'] = $request->observaciones;
            } elseif ($request->tiene_observaciones === false) {
                $data['observaciones'] = null;
            }
        }

        return $data;
    }
    public function deleteExcel($id)
    {
        try {
            $importProducto = ImportProducto::find($id);
            if (!$importProducto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Importación no encontrada'
                ], 404);
            }

            // Eliminar el archivo de la base de datos
            $importProducto->delete();

            $filePath = storage_path('app/' . $importProducto->ruta_archivo);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Importación eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar importación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar importación: ' . $e->getMessage()
            ], 500);
        }
    }
    public function obtenerListExcel(){
        $importProductos = ImportProducto::all();
        return response()->json([
            'success' => true,
            'data' => $importProductos
        ]);
    }
}
