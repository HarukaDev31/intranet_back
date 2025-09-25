<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ImportacionesController extends Controller
{
    public function getTrayectos(Request $request)
    {
        try {
            //get current user whatsapp number from jwt
            $user = JWTAuth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 401);
            }
            $whatsapp = $user->whatsapp;
            Log::info('Whatsapp: ' . $whatsapp);
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            $trayectos = Cotizacion::with(['contenedor' => function ($query) {
                $query->select('id', 'carga', 'fecha_arribo', 'f_entrega', 'f_cierre');
            }])
                ->with(['proveedores' => function ($query) {
                    $query->select('id_cotizacion', 'cbm_total', 'qty_box', 'qty_box_china', 'cbm_total_china', 'estados_proveedor');
                }])
                ->where('estado_cotizador', 'CONFIRMADO')
                ->whereNull('id_cliente_importacion')
                //where telefono trim and remove +51 from db
                ->where(DB::raw('TRIM(telefono)'), 'like', '%' . $whatsapp . '%')
                ->whereNotNull('estado_cliente')
                ->select('id', 'id_contenedor', 'qty_item', 'volumen_final', 'fob_final', 'logistica_final', 'fob', 'monto', 'estado_cliente')
                ->orderBy('id', 'desc')
                ->paginate($perPage);

            // Transformar los datos para incluir la información del contenedor
            $trayectosData = $trayectos->getCollection()->map(function ($cotizacion) {
                return [
                    'id' => $cotizacion->id,
                    'id_contenedor' => $cotizacion->id_contenedor,
                    'carga' => $cotizacion->contenedor ? $cotizacion->contenedor->carga : null,
                    'fecha_cierre' => $cotizacion->contenedor ? $cotizacion->contenedor->f_cierre : null,
                    'fecha_arribo' => $cotizacion->contenedor ? $cotizacion->contenedor->fecha_arribo : null,
                    'fecha_entrega' => $cotizacion->contenedor ? $cotizacion->contenedor->f_entrega : null,
                    'qty_box' => $cotizacion->getSumQtyBoxChinaAttribute(),
                    'cbm' => $cotizacion->getSumCbmTotalChinaAttribute(),
                    'fob' => $cotizacion->fob_final ?? $cotizacion->fob,
                    'logistica' => $cotizacion->logistica_final ?? $cotizacion->monto,
                    'estado_cliente' => $cotizacion->estado_cliente,
                    'seguimiento' => null, // Agregar lógica según tu modelo
                    'inspecciones' => null // Agregar lógica según tu modelo
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $trayectosData,
                'pagination' => [
                    'total' => $trayectos->total(),
                    'per_page' => $trayectos->perPage(),
                    'current_page' => $trayectos->currentPage(),
                    'last_page' => $trayectos->lastPage(),
                    'from' => $trayectos->firstItem(),
                    'to' => $trayectos->lastItem()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener trayectos: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }
    public function getInspecciones(Request $request, $idCotizacion)
    {
        try {
            // Obtener usuario actual
            $user = JWTAuth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 401);
            }
            
            $whatsapp = $user->whatsapp;
            
            // Validar que la cotización pertenece al cliente actual
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
                ->where('main.id', $idCotizacion) // Validar ID específico
                ->where(DB::raw('TRIM(main.telefono)'), 'like', '%' . $whatsapp . '%') // Validar que pertenece al cliente
                ->where('main.estado_cotizador', 'CONFIRMADO')
                ->whereNull('main.id_cliente_importacion')
                ->whereNotNull('main.estado_cliente')
                ->first();

            // Si no encuentra la cotización, significa que no pertenece al cliente o no existe
            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada o no autorizada',
                    'data' => null
                ], 404);
            }

            // Decodificar los JSON arrays
            $cotizacion->files = json_decode($cotizacion->files, true) ?? [];
            $cotizacion->files_almacen_documentacion = json_decode($cotizacion->files_almacen_documentacion, true) ?? [];
            $cotizacion->providers = json_decode($cotizacion->providers, true) ?? [];
            $cotizacion->files_almacen_inspection = json_decode($cotizacion->files_almacen_inspection, true) ?? [];

            return response()->json([
                'success' => true,
                'data' => $cotizacion
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inspecciones: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
