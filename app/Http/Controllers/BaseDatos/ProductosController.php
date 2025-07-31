<?php

namespace App\Http\Controllers\BaseDatos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\BaseDatos\ProductoImportadoExcel;
use App\Exports\ProductosExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use App\Models\BaseDatos\CargaConsolidadaContenedor;

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
            $query = ProductoImportadoExcel::join('carga_consolidada_contenedor', 'productos_importados_excel.idContenedor', '=', 'carga_consolidada_contenedor.id')
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
            $cargas = CargaConsolidadaContenedor::getCargasUnicas();

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

            $campanas = CargaConsolidadaContenedor::select('carga')
                ->whereNotNull('carga')
                ->where('carga', '!=', '')
                ->distinct()
                ->orderBy('carga')
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
            'link', 'arancel_sunat', 'arancel_tlc', 'correlativo', 
            'antidumping', 'tipo_producto', 'etiquetado', 'doc_especial'
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
}
