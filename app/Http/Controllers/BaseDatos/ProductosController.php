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
use Illuminate\Support\Facades\Storage;
use App\Jobs\ImportProductosExcelJob;

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
                ->select('productos_importados_excel.id',
                    'productos_importados_excel.nombre_comercial',
                    'productos_importados_excel.subpartida',
                    'productos_importados_excel.rubro',
                    'productos_importados_excel.tipo_producto',
                    'productos_importados_excel.foto',
                    'productos_importados_excel.unidad_comercial',
                    'carga_consolidada_contenedor.carga as carga_contenedor',
                    DB::raw('YEAR(carga_consolidada_contenedor.f_cierre) as anio')
                );

            // Aplicar filtros si están presentes (reemplaza el bloque actual)
            if ($request->has('search') && $request->search) {
                $query->where(function ($q) use ($request) {
                    $q->where('productos_importados_excel.nombre_comercial', 'like', '%' . $request->search . '%')
                        ->orWhere('productos_importados_excel.subpartida', 'like', '%' . $request->search . '%');
                });
            }

            // Filtrar por rubro si viene y no es 'todos'
            if ($request->has('rubro') && $request->rubro && $request->rubro !== 'todos') {
                $query->where('productos_importados_excel.rubro', $request->rubro);
            }

            // Filtrar por tipo de producto (frontend envía tipoProducto) -> columna tipo_producto
            if ($request->has('tipoProducto') && $request->tipoProducto && $request->tipoProducto !== 'todos') {
                $query->where('productos_importados_excel.tipo_producto', $request->tipoProducto);
            }

            // Filtrar por campaña (join) -> usar la columna completa de la tabla join
            if ($request->has('campana') && $request->campana && $request->campana !== 'todos') {
                $query->where('carga_consolidada_contenedor.carga', $request->campana);
            }

            // Ordenar por carga consolidada más reciente y por año de cierre (descendente)
            $query->orderByRaw('CAST(carga_consolidada_contenedor.carga AS UNSIGNED) DESC')
                ->orderByRaw('YEAR(carga_consolidada_contenedor.f_cierre) DESC');

            $data = $query->paginate($perPage, ['*'], 'page', $page);
            //for each foto add the url if url cannot contains http or https    
            foreach ($data->items() as $item) {
                if (!strpos($item->foto, 'http') && !strpos($item->foto, 'https')) {
                    $item->foto = $this->generateImageUrl($item->foto);
                }
            }
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
            // Obtener solo las cargas que tienen productos (disponibles)
            $cargas = ProductoImportadoExcel::leftJoin('carga_consolidada_contenedor', 'productos_importados_excel.idContenedor', '=', 'carga_consolidada_contenedor.id')
                ->whereNotNull('carga_consolidada_contenedor.carga')
                ->where('carga_consolidada_contenedor.carga', '!=', '')
                ->distinct()
                ->orderByRaw('CAST(carga_consolidada_contenedor.carga AS UNSIGNED)')
                ->pluck('carga_consolidada_contenedor.carga')
                ->map(function ($c) {
                    return trim((string)$c);
                })
                ->filter()
                ->values()
                ->toArray();

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
        DB::beginTransaction();
        try {
            Log::info('Iniciando importación de productos');

            $file = $request->file('excel_file');
            $idContenedor = $request->idContenedor;
            Log::info('prepared Request idContenedor: ' . $idContenedor);
            try {
                Log::info('Request dump (keys): ' . json_encode(array_keys($request->all())));
                Log::info('Request files keys: ' . json_encode(array_keys($request->files->all())));
            } catch (\Exception $e) {
                Log::warning('No se pudo volcar request para debug: ' . $e->getMessage());
            }
            if (!$file) {
                Log::error('No se proporcionó archivo');
                return response()->json([
                    'success' => false,
                    'message' => 'No se proporcionó archivo Excel'
                ], 400);
            }

            // Validar el archivo con Laravel
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls,xlsm'
            ]);
            Log::info('Validation passed for excel_file: ' . $file->getClientOriginalName());

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

            //s
            $tempPath = $file->store('temp');
            $fullTempPath = storage_path('app/' . $tempPath);
            $filePath = $file->storeAs('imports/productos', time() . '_' . uniqid() . '_' . $file->getClientOriginalName(), 'public');

            // Crear registro de importación
            $importProducto = ImportProducto::create([
                'nombre_archivo' => $file->getClientOriginalName(),
                'cantidad_rows' => 0,
                'ruta_archivo' => $filePath,
                'estadisticas' => [
                    'status' => 'processing',
                    'message' => 'Importación iniciada'
                ],
                'id_contenedor_consolidado_documentacion_files' => $idContenedor
            ]);

            // Log para verificar qué se guardó exactamente en el registro de importación
            try {
                Log::info('ImportProducto creado - id: ' . $importProducto->id . ', id_contenedor_consolidado_documentacion_files: ' . ($importProducto->id_contenedor_consolidado_documentacion_files ?? 'NULL') . ', id_contenedor (fallback): ' . ($importProducto->id_contenedor ?? 'NULL'));
            } catch (\Exception $e) {
                Log::warning('No se pudo loguear ImportProducto creado: ' . $e->getMessage());
            }
            DB::commit();

            ImportProductosExcelJob::dispatch($fullTempPath, $importProducto->id)->onQueue('importaciones');
            return response()->json([
                'success' => true,
                'message' => 'Importación iniciada correctamente. El procesamiento se realizará en segundo plano.',
                'data' => [
                    'import_id' => $importProducto->id,
                    'import_info' => $importProducto,
                    'status' => 'processing',
                    'polling_url' => url("/api/productos/import/status/{$importProducto->id}")
                ]
            ]);
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
            }
            // Si el switch está en false, NO borres el campo observaciones
        }

        return $data;
    }
    public function deleteExcel($id)
    {
        try {
            Log::info('ProductosController::deleteExcel called with id=' . $id);
            $importProducto = ImportProducto::find($id);

            if (!$importProducto) {
                Log::warning('ProductosController::deleteExcel - import not found id=' . $id);
                return response()->json([
                    'success' => false,
                    'message' => 'Importación no encontrada'
                ], 404);
            }

            Log::info('ProductosController::deleteExcel - import found id=' . $importProducto->id . ', id_contenedor_consolidado_documentacion_files=' . ($importProducto->id_contenedor_consolidado_documentacion_files ?? 'NULL') . ', ruta_archivo=' . ($importProducto->ruta_archivo ?? 'NULL'));

            // Eliminar productos importados asociados a esta importación (si los hay)
            try {
                $deletedCount = ProductoImportadoExcel::where('id_import_producto', $importProducto->id)->delete();
                Log::info('Productos importados eliminados para import_id=' . $importProducto->id . ': ' . $deletedCount);
            } catch (\Exception $e) {
                Log::warning('No se pudieron eliminar productos importados para import_id=' . $importProducto->id . ': ' . $e->getMessage());
            }

            // Eliminar el registro de importación
            try {
                $importProducto->delete();
            } catch (\Exception $e) {
                Log::warning('No se pudo eliminar ImportProducto id=' . $importProducto->id . ': ' . $e->getMessage());
            }

            // Eliminar archivo físico asociado a la importación
            $filePath = storage_path('app/' . $importProducto->ruta_archivo);
            if (!empty($importProducto->ruta_archivo) && file_exists($filePath)) {
                try {
                    unlink($filePath);
                } catch (\Exception $e) {
                    Log::warning('No se pudo eliminar el archivo físico de la importación: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Importación y productos asociados eliminados correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar importación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar importación: ' . $e->getMessage()
            ], 500);
        }
    }
    public function obtenerListExcel()
    {
        $importProductos = ImportProducto::all();
        //foreach ruta_archivo 
        foreach ($importProductos as $importProducto) {
            $importProducto->ruta_archivo = $this->generateImageUrl($importProducto->ruta_archivo);
        }
        return response()->json([
            'success' => true,
            'data' => $importProductos
        ]);
    }

    /**
     * Verificar estado de importación
     */
    public function checkImportStatus($id)
    {
        try {
            $importProducto = ImportProducto::find($id);

            if (!$importProducto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Importación no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $importProducto->id,
                    'nombre_archivo' => $importProducto->nombre_archivo,
                    'cantidad_rows' => $importProducto->cantidad_rows,
                    'estadisticas' => $importProducto->estadisticas,
                    'created_at' => $importProducto->created_at,
                    'updated_at' => $importProducto->updated_at
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al verificar estado de importación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar estado de importación: ' . $e->getMessage()
            ], 500);
        }
    }
    private function generateImageUrl($ruta)
    {
        if (empty($ruta)) {
            return null;
        }

        // Si ya es una URL completa, devolverla tal como está
        if (filter_var($ruta, FILTER_VALIDATE_URL)) {
            return $ruta;
        }

        // Limpiar la ruta de barras iniciales para evitar doble slash
        $ruta = ltrim($ruta, '/');

        // Construir URL manualmente para evitar problemas con Storage::url()
        $baseUrl = config('app.url');
        $storagePath = '/storage/';

        // Asegurar que no haya doble slash
        $baseUrl = rtrim($baseUrl, '/');
        $storagePath = ltrim($storagePath, '/');
        $ruta = ltrim($ruta, '/');
        return $baseUrl . '/' . $storagePath . '/' . $ruta;
    }
}
