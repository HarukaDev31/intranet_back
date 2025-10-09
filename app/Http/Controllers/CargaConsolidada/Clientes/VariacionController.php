<?php

namespace App\Http\Controllers\CargaConsolidada\Clientes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VariacionController extends Controller
{
    public function index(Request $request, $idContenedor)
    {
        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->select([
                'CC.*',
                'C.*',
                'CC.id as id_cotizacion',
                'TC.name as name',
                'U.No_Nombres_Apellidos as asesor',
                DB::raw("CONCAT(
                    C.carga,
                    DATE_FORMAT(CC.fecha, '%d%m%y'),
                    UPPER(LEFT(TRIM(CC.nombre), 3))
                ) as COD")
            ])
            ->join('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
            ->join('contenedor_consolidado_tipo_cliente as TC', 'TC.id', '=', 'CC.id_tipo_cliente')
            ->leftJoin('usuario as U', 'U.ID_Usuario', '=', 'CC.id_usuario')
            ->where('CC.id_contenedor', $idContenedor)
            ->whereNotNull('CC.estado_cliente')
            ->whereNull('id_cliente_importacion')
            ->where('CC.estado_cotizador', 'CONFIRMADO')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('contenedor_consolidado_cotizacion_proveedores')
                    ->whereColumn('contenedor_consolidado_cotizacion_proveedores.id_cotizacion', 'CC.id');
            });
        // Aplicar filtro de estado si se proporciona
        $estado = $request->input('estado', '0');
        if ($estado !== '0') {
            $query->where('CC.estado', $estado);
        }

        // Aplicar filtros adicionales si se proporcionan
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('CC.nombre', 'LIKE', "%{$search}%")
                    ->orWhere('CC.documento', 'LIKE', "%{$search}%")
                    ->orWhere('CC.correo', 'LIKE', "%{$search}%");
            });
        }

        // Ordenamiento
        $sortField = $request->input('sort_by', 'CC.fecha');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortField, $sortOrder);

        // Paginación
        $perPage = $request->input('per_page', 100);
        $data = $query->paginate($perPage);

        return response()->json([
            'data' => $data->items(),
            'success' => true,
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem()
            ]
        ]);
    }

    /**
     * Obtiene la documentación completa de un cliente específico
     */
    public function showClientesDocumentacion($id)
    {
        try {
            // Obtener la cotización principal
            $cotizacion = DB::table('contenedor_consolidado_cotizacion as main')
                ->select([
                    'main.*',
                    DB::raw("(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', docs.id,
                                'file_url', docs.file_url,
                                'folder_name', docs.name,
                                'id_proveedor', docs.id_proveedor
                            )
                        )
                        FROM contenedor_consolidado_cotizacion_documentacion docs
                        WHERE docs.id_cotizacion = main.id
                    ) as files"),
                    DB::raw("(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', almacen_docs.id,
                                'file_url', almacen_docs.file_path,
                                'folder_name', almacen_docs.file_name,
                                'file_name', almacen_docs.file_name,
                                'id_proveedor', almacen_docs.id_proveedor,
                                'file_ext', almacen_docs.file_ext
                            )
                        )
                        FROM contenedor_consolidado_almacen_documentacion almacen_docs
                        WHERE almacen_docs.id_cotizacion = main.id
                    ) as files_almacen_documentacion"),
                    DB::raw("(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'code_supplier', prov.code_supplier,
                                'id', prov.id,
                                'volumen_doc', prov.volumen_doc,
                                'valor_doc', prov.valor_doc,
                                'factura_comercial', prov.factura_comercial,
                                'excel_confirmacion', prov.excel_confirmacion,
                                'packing_list', prov.packing_list
                            )
                        )
                        FROM contenedor_consolidado_cotizacion_proveedores prov
                        WHERE prov.id_cotizacion = main.id
                    ) as providers"),
                    DB::raw("(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', inspection_docs.id,
                                'file_url', inspection_docs.file_path,
                                'file_name', inspection_docs.file_name,
                                'id_proveedor', inspection_docs.id_proveedor,
                                'file_ext', inspection_docs.file_type
                            )
                        )
                        FROM contenedor_consolidado_almacen_inspection inspection_docs
                        WHERE inspection_docs.id_cotizacion = main.id
                    ) as files_almacen_inspection")
                ])
                ->where('main.id', $id)
                ->whereNotNull('main.estado')
                ->first();

            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada o sin estado válido'
                ], 404);
            }

            // Decodificar los campos JSON si existen
            if ($cotizacion->files) {
                $cotizacion->files = json_decode($cotizacion->files, true) ?: [];
            } else {
                $cotizacion->files = [];
            }

            if ($cotizacion->files_almacen_documentacion) {
                $cotizacion->files_almacen_documentacion = json_decode($cotizacion->files_almacen_documentacion, true) ?: [];
            } else {
                $cotizacion->files_almacen_documentacion = [];
            }

            if ($cotizacion->providers) {
                $cotizacion->providers = json_decode($cotizacion->providers, true) ?: [];
            } else {
                $cotizacion->providers = [];
            }

            if ($cotizacion->files_almacen_inspection) {
                $cotizacion->files_almacen_inspection = json_decode($cotizacion->files_almacen_inspection, true) ?: [];
            } else {
                $cotizacion->files_almacen_inspection = [];
            }
            // Procesar documentos de almacén
            foreach ($cotizacion->files_almacen_documentacion as &$file) {
                if (isset($file['file_url'])) {
                    $file['file_url'] = $this->generateImageUrl($file['file_url']);
                }
            }

            // Procesar documentos de inspección
            foreach ($cotizacion->files_almacen_inspection as &$file) {
                if (isset($file['file_url'])) {
                    $file['file_url'] = $this->generateImageUrl($file['file_url']);
                }
            }

            // Procesar documentos de proveedores
            foreach ($cotizacion->providers as &$provider) {
                if (isset($provider['factura_comercial'])) {
                    $provider['factura_comercial'] = $this->generateImageUrl($provider['factura_comercial']);
                }
                if (isset($provider['packing_list'])) {
                    $provider['packing_list'] = $this->generateImageUrl($provider['packing_list']);
                }
                if (isset($provider['excel_confirmacion'])) {
                    $provider['excel_confirmacion'] = $this->generateImageUrl($provider['excel_confirmacion']);
                }
            }
            return response()->json([
                'success' => true,
                'data' => $cotizacion,
                'message' => 'Documentación de cliente obtenida exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener documentación: ' . $e->getMessage()
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
    public function updateVolSelected(Request $request)
    {
        try {
            $idCotizacion = $request->id_cotizacion;
            $volSelected = $request->volumen;
            $cotizacion = DB::table('contenedor_consolidado_cotizacion')
                ->where('id', $idCotizacion)
                ->update(['vol_selected' => $volSelected]);
            if ($cotizacion) {
                return response()->json([
                    'success' => true,
                    'message' => 'Volumen seleccionado actualizado correctamente'
                ]);
            }
            return response()->json([
                'success' => false,
                'message' => 'Cotización no encontrada'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el volumen seleccionado: ' . $e->getMessage()
            ], 500);
        }
    }
}
