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

            // Usar query builder en lugar de Collection
            $query = ProductoImportadoExcel::query();

            // Aplicar filtros si están presentes
            if ($request->has('search') && $request->search) {
                $query->where('nombre', 'like', '%' . $request->search . '%')
                    ->orWhere('descripcion', 'like', '%' . $request->search . '%');
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
}
