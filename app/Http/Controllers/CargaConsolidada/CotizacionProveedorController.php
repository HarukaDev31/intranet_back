<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\AlmacenDocumentacion;
use App\Models\CargaConsolidada\AlmacenInspection;
use App\Models\Usuario;
use App\Models\TipoCliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class CotizacionProveedorController extends Controller
{
    /**
     * Obtener cotizaciones con proveedores por contenedor
     */
    public function getContenedorCotizacionProveedores(Request $request, $idContenedor)
    {
        try {
            // Obtener usuario autenticado
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $rol=$user->getNombreGrupo();
            
            $estadoChina = $request->estado_china??'todos';
            $search = $request->search??'';
            $page = $request->currentPage??1;
            $perPage = $request->itemsPerPage??10   ;
            $query = DB::table('contenedor_consolidado_cotizacion AS main')
                ->select([
                    'main.*',
                    'U.No_Nombres_Apellidos',
                    DB::raw('(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                "id", proveedores.id,
                                "qty_box", proveedores.qty_box,
                                "peso", proveedores.peso,
                                "cbm_total", proveedores.cbm_total,
                                "supplier", proveedores.supplier,
                                "code_supplier", proveedores.code_supplier,
                                "estados_proveedor", proveedores.estados_proveedor,
                                "estados", proveedores.estados,
                                "supplier_phone", proveedores.supplier_phone,
                                "cbm_total_china", proveedores.cbm_total_china,
                                "qty_box_china", proveedores.qty_box_china,
                                "id_proveedor", proveedores.id,
                                "products", proveedores.products,
                                "estado_china", proveedores.estado_china,
                                "arrive_date_china", proveedores.arrive_date_china,
                                "send_rotulado_status", proveedores.send_rotulado_status
                            )
                        )
                        FROM contenedor_consolidado_cotizacion_proveedores proveedores
                        WHERE proveedores.id_cotizacion = main.id
                    ) as proveedores')
                ])
                ->join('contenedor_consolidado_tipo_cliente AS TC', 'TC.id', '=', 'main.id_tipo_cliente')
                ->leftJoin('usuario AS U', 'U.ID_Usuario', '=', 'main.id_usuario')
                ->where('main.id_contenedor', $idContenedor);
            
            if (!empty($search)) {
                $query->where('main.nombre', 'LIKE', '%' . $search . '%');
            }
            
            $query->orderBy('main.id', 'asc');

            if ($rol != Usuario::ROL_COTIZADOR) {
                $query->where('estado_cotizador', 'CONFIRMADO');

            } else if ($rol == Usuario::ROL_COTIZADOR && $user->ID_Usuario != 28791) {
                $query->where('main.id_usuario', $user->ID_Usuario);
                $query->orderBy('fecha_confirmacion', 'asc');
            } else {
              
            }

            if ($rol == Usuario::ROL_COTIZADOR) {
                $query->orderBy('main.fecha_confirmacion', 'asc');
            }

     
            // Ejecutar consulta con paginación
            $data = $query->paginate($perPage, ['*'], 'page', $page);
            $estadoChina = $request->estado_china;
         

            // Procesar datos para el frontend
            $dataProcessed = collect($data->items())->map(function ($item) use ($user, $estadoChina, $rol, $search) {
                $proveedores = json_decode($item->proveedores, true) ?: [];
                
                // Filtrar proveedores por estado_china si es necesario
                if ($rol == Usuario::ROL_ALMACEN_CHINA && $estadoChina != "todos") {
                    $proveedores = array_filter($proveedores, function ($proveedor) use ($estadoChina) {
                        return ($proveedor['estados_proveedor'] ?? '') === $estadoChina;
                    });
                }
                //if proveedores is empty not show the item
                if (empty($proveedores)) {
                    return null;
                }
                
                $cbmTotalChina = 0;
                $cbmTotalPeru = 0;
                
                foreach ($proveedores as $proveedor) {
                    if (is_numeric($proveedor['cbm_total_china'] ?? null)) {
                        $cbmTotalChina += $proveedor['cbm_total_china'];
                    }
                    if (is_numeric($proveedor['cbm_total'] ?? null)) {
                        $cbmTotalPeru += $proveedor['cbm_total'];
                    }
                }

                return [
                    'id' => $item->id,
                    'id_contenedor' => $item->id_contenedor,
                    'id_usuario' => $item->id_usuario,
                    'id_tipo_cliente' => $item->id_tipo_cliente,
                    'nombre' => $item->nombre,
                    'telefono' => $item->telefono,
                    'estado_cotizador' => $item->estado_cotizador,
                    'fecha_confirmacion' => $item->fecha_confirmacion,
                    'No_Nombres_Apellidos' => $item->No_Nombres_Apellidos,
                    'proveedores' => $proveedores, // Proveedores ya filtrados
                    'totales' => [
                        'cbm_total_china' => $cbmTotalChina,
                        'cbm_total_peru' => $cbmTotalPeru
                    ],
                  
                ];
            })->filter()->values();
            


            // Obtener opciones de filtro

            return response()->json([
                'success' => true,
                'data' => $dataProcessed,
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ],
     
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cotizaciones con proveedores',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateEstadoProveedor(Request $request, $idCotizacion, $idProveedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $request->validate([
                'estado' => 'required|string'
            ]);

            $proveedor = CotizacionProveedor::where('id_cotizacion', $idCotizacion)
                ->where('id', $idProveedor)
                ->first();

            if (!$proveedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado'
                ], 404);
            }

            $proveedor->estados_proveedor = $request->estado;
            $proveedor->save();

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado correctamente',
                'data' => $proveedor
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar estado de cotización proveedor
     */
    public function updateEstadoCotizacionProveedor(Request $request, $idCotizacion, $idProveedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $request->validate([
                'estado' => 'required|string'
            ]);

            $proveedor = CotizacionProveedor::where('id_cotizacion', $idCotizacion)
                ->where('id', $idProveedor)
                ->first();

            if (!$proveedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado'
                ], 404);
            }

            $proveedor->estados = $request->estado;
            $proveedor->save();

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado correctamente',
                'data' => $proveedor
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar datos del proveedor
     */
    public function updateProveedorData(Request $request, $idCotizacion, $idProveedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $request->validate([
                'supplier' => 'nullable|string',
                'code_supplier' => 'nullable|string',
                'supplier_phone' => 'nullable|string',
                'products' => 'nullable|string',
                'qty_box_china' => 'nullable|integer',
                'cbm_total_china' => 'nullable|numeric',
                'arrive_date_china' => 'nullable|date'
            ]);

            $proveedor = CotizacionProveedor::where('id_cotizacion', $idCotizacion)
                ->where('id', $idProveedor)
                ->first();

            if (!$proveedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado'
                ], 404);
            }

            $proveedor->update($request->only([
                'supplier', 'code_supplier', 'supplier_phone', 'products',
                'qty_box_china', 'cbm_total_china', 'arrive_date_china'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Datos del proveedor actualizados correctamente',
                'data' => $proveedor
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar datos del proveedor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar estado de rotulado
     */
    public function updateRotulado(Request $request, $idCotizacion, $idProveedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            if (!in_array($user->No_Grupo, ['GERENCIA', 'Coordinación'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para realizar esta acción'
                ], 403);
            }

            $proveedor = CotizacionProveedor::where('id_cotizacion', $idCotizacion)
                ->where('id', $idProveedor)
                ->first();

            if (!$proveedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado'
                ], 404);
            }

            // Cambiar estado de rotulado a pendiente
            $proveedor->estados = 'ROTULADO';
            $proveedor->send_rotulado_status = 'PENDING';
            $proveedor->save();

            return response()->json([
                'success' => true,
                'message' => 'Estado de rotulado actualizado correctamente',
                'data' => $proveedor
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado de rotulado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar cotización
     */
    public function deleteCotizacion(Request $request, $idCotizacion, $idProveedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            if ($user->No_Grupo !== "Coordinación") {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para realizar esta acción'
                ], 403);
            }

            $proveedor = CotizacionProveedor::where('id_cotizacion', $idCotizacion)
                ->where('id', $idProveedor)
                ->first();

            if (!$proveedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado'
                ], 404);
            }

            $proveedor->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cotización eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar cotización',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener archivos de documentación del almacén
     */
    public function getFilesAlmacenDocument($idProveedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $files = AlmacenDocumentacion::where('id_proveedor', $idProveedor)
                ->select([
                    'id',
                    'file_name',
                    'file_path as file_url',
                    'file_type as type',
                    'file_size as size',
                    'last_modified as lastModified',
                    'file_ext'
                ])
                ->get();

            return response()->json([
                'success' => true,
                'status' => 'success',
                'error' => false,
                'data' => $files
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getFilesAlmacenDocument: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar archivo de documentación
     */
    public function deleteFileDocumentation($idFile)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $file = AlmacenDocumentacion::find($idFile);
            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo no encontrado'
                ], 404);
            }

            // Eliminar archivo del sistema de archivos
            if (Storage::exists($file->file_path)) {
                Storage::delete($file->file_path);
            }

            // Eliminar registro de la base de datos
            $file->delete();

            return response()->json([
                'success' => true,
                'message' => 'Archivo eliminado correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en deleteFileDocumentation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener archivos de inspección del almacén
     */
    public function getFilesAlmacenInspection($idProveedor)
    {
        try {
           

            $files = AlmacenInspection::where('id_proveedor', $idProveedor)
                ->select([
                    'id',
                    'file_name',
                    'file_path as file_url',
                    'file_type as type',
                    'file_size as size',
                    'last_modified as lastModified',
                    'file_type as file_ext',
                    'send_status'
                ])
                ->get();

            return response()->json([
                'success' => true,
                'status' => 'success',
                'error' => false,
                'data' => $files
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getFilesAlmacenInspection: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar y enviar mensaje de inspección
     */
    public function validateToSendInspectionMessage($idProveedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            Log::info("validateToSendInspectionMessage: " . $idProveedor);

            // Obtener imágenes del proveedor
            $imagesUrls = AlmacenInspection::where('id_proveedor', $idProveedor)
                ->whereIn('file_type', ['image/jpeg', 'image/png', 'image/jpg'])
                ->select(['id', 'file_path', 'file_type', 'send_status'])
                ->get();

            // Obtener videos del proveedor
            $videosUrls = AlmacenInspection::where('id_proveedor', $idProveedor)
                ->where('file_type', 'video/mp4')
                ->select(['id', 'file_path', 'file_type', 'send_status'])
                ->get();

            // Obtener datos del proveedor
            $proveedor = CotizacionProveedor::where('id', $idProveedor)
                ->select(['estados_proveedor', 'code_supplier', 'qty_box_china', 'qty_box', 'id_cotizacion'])
                ->first();

            if (!$proveedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado'
                ], 404);
            }

            // Obtener datos de la cotización
            $cotizacion = Cotizacion::where('id', $proveedor->id_cotizacion)
                ->select(['volumen', 'monto', 'id_contenedor', 'nombre', 'telefono'])
                ->first();

            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ], 404);
            }

            // Obtener fecha de cierre del contenedor
            $contenedor = DB::table('contenedor_consolidado')->where('id', $cotizacion->id_contenedor)->first();
            $fCierre = $contenedor ? $contenedor->f_cierre : null;

            // Formatear fecha de cierre
            if ($fCierre) {
                $fCierre = Carbon::parse($fCierre)->format('d F');
                $meses = [
                    'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo',
                    'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
                    'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
                    'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
                ];
                $fCierre = str_replace(array_keys($meses), array_values($meses), $fCierre);
            }

            // Actualizar estado del proveedor
            $proveedorUpdate = CotizacionProveedor::find($idProveedor);
            $proveedorUpdate->estados_proveedor = 'INSPECTION';
            $proveedorUpdate->estados = 'INSPECCIONADO';
            $proveedorUpdate->save();

            $message = "Se ha actualizado el proveedor con código de proveedor " . $proveedor->code_supplier . " a estado INSPECCIONADO";

            // Preparar datos para el mensaje
            $cliente = $cotizacion->nombre;
            $telefono = $cotizacion->telefono;
            $telefono = preg_replace('/\s+/', '', $telefono);
            $telefono .= $telefono ? '@c.us' : '';

            // Verificar si se puede enviar mensaje
            $sendStatus = true;
            foreach ($imagesUrls as $image) {
                if ($image->send_status == 'SENDED') {
                    $sendStatus = false;
                }
            }
            foreach ($videosUrls as $video) {
                if ($video->send_status == 'SENDED') {
                    $sendStatus = false;
                }
            }

            if ($sendStatus) {
                $qtyBox = $proveedor->qty_box_china ?? $proveedor->qty_box;
                $message = $cliente . '----' . $proveedor->code_supplier . '----' . $qtyBox . ' boxes. ' . "\n\n" . 
                          '📦 Tu carga llegó a nuestro almacén de Yiwu, te comparto las fotos y videos. ' . "\n\n";
                
                // Aquí se simularía el envío del mensaje principal
                Log::info('Enviando mensaje principal: Hola buen día 🙋🏻‍♀' . "\n\n" . 'Inspección: ' . "\n" . $message);
            }

            // Filtrar archivos pendientes de envío
            $imagesPendientes = $imagesUrls->where('send_status', 'PENDING');
            $videosPendientes = $videosUrls->where('send_status', 'PENDING');

            // Simular envío de medios de inspección
            foreach ($imagesPendientes as $image) {
                Log::info('Enviando imagen de inspección: ' . $image->file_path);
                // Aquí se simularía el envío real del medio
            }

            foreach ($videosPendientes as $video) {
                Log::info('Enviando video de inspección: ' . $video->file_path);
                // Aquí se simularía el envío real del medio
            }

            return response()->json([
                'success' => true,
                'message' => 'Proceso de inspección completado correctamente',
                'data' => [
                    'proveedor_actualizado' => true,
                    'imagenes_enviadas' => $imagesPendientes->count(),
                    'videos_enviados' => $videosPendientes->count(),
                    'mensaje_enviado' => $sendStatus
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en validateToSendInspectionMessage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la inspección',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener notas del proveedor
     */
    public function getNotes($idProveedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $proveedor = CotizacionProveedor::where('id', $idProveedor)
                ->select('nota')
                ->first();

            if (!$proveedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'nota' => $proveedor->nota
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getNotes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener notas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar o actualizar nota del proveedor
     */
    public function addNote(Request $request, $idProveedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $request->validate([
                'nota' => 'required|string|max:1000'
            ]);

            $proveedor = CotizacionProveedor::find($idProveedor);
            if (!$proveedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado'
                ], 404);
            }

            $proveedor->nota = $request->nota;
            $proveedor->save();

            return response()->json([
                'success' => true,
                'message' => 'Nota actualizada correctamente',
                'data' => [
                    'nota' => $proveedor->nota
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en addNote: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar nota',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getCotizacionProveedor($idProveedor){
        try {
            $proveedor = CotizacionProveedor::with('contenedor')
                ->where('id', $idProveedor)
                ->first();

            if (!$proveedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $proveedor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cotización proveedor',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
