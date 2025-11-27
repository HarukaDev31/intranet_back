<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\AlmacenDocumentacion;
use App\Models\CargaConsolidada\AlmacenInspection;
use App\Models\CargaConsolidada\Contenedor as CargaConsolidadaContenedor;
use App\Models\Usuario;
use App\Models\TipoCliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;
use App\Traits\WhatsappTrait;
use App\Models\ContenedorCotizacionProveedor;
use App\Jobs\SendInspectionMediaJob;
use App\Jobs\ForceSendCobrandoJob;
use App\Jobs\ForceSendRotuladoJob;
use App\Jobs\SendRecordatorioDatosProveedorJob;
use App\Models\ContenedorCotizacion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Dompdf\Dompdf;
use Dompdf\Options;
use ZipArchive;
use Exception;
use App\Models\CargaConsolidada\Contenedor;
use Illuminate\Support\Str;
use App\Traits\UserGroupsTrait;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EmbarqueExport;
use App\Jobs\SendRotuladoJob;
use App\Models\CargaConsolidada\DocumentacionFile;
use App\Models\CargaConsolidada\DocumentacionFolder;
use App\Models\CargaConsolidada\CotizacionDocumentacion;
use App\Events\CotizacionChinaContacted;
use App\Events\CotizacionChinaReceived;
use App\Events\CotizacionChinaInspected;
use App\Models\Notificacion;

class CotizacionProveedorController extends Controller
{
    use WhatsappTrait;
    use UserGroupsTrait;
    const DOCUMENTATION_PATH = 'documentation';
    const INSPECTION_PATH = 'inspection';
    private $providerOrderStatus = [
        "NC" => 0,
        "C" => 1,
        "R" => 2,
        "NS" => 3,
        "INSPECTION" => 4,
        'LOADED' => 5,
        'NO LOADED' => 6
    ];
    private $providerCoordinacionOrderStatus = [
        "ROTULADO" => 0,
        'DATOS PROVEEDOR' => 1,
        'COBRANDO' => 2,
        'INSPECCIONADO' => 3,
        'RESERVADO' => 4,
        'NO RESERVADO' => 5,
        'EMBARCADO' => 6,
        'NO EMBARCADO' => 7,
    ];
    private $STATUS_NOT_CONTACTED = "NC";
    private $STATUS_CONTACTED = "C";
    private $STATUS_RECIVED = "R";
    private $STATUS_NOT_SELECTED = "NS";
    private $STATUS_INSPECTION = "INSPECTION";
    private $STATUS_LOADED = "LOADED";
    private $STATUS_NO_LOADED = "NO LOADED";
    private $STATUS_ROTULADO = "ROTULADO";
    private $STATUS_DATOS_PROVEEDOR = "DATOS PROVEEDOR";
    private $STATUS_COBRANDO = "COBRANDO";
    private $STATUS_INSPECCIONADO = "INSPECCIONADO";
    private $STATUS_RESERVADO = "RESERVADO";
    private $STATUS_NO_RESERVADO = "NO RESERVADO";
    private $STATUS_EMBARCADO = "EMBARCADO";
    private $STATUS_NO_EMBARCADO = "NO EMBARCADO";
    private $table_pais = "pais";
    private $table_contenedor_steps = "contenedor_consolidado_order_steps";
    private $table_contenedor_cotizacion = "contenedor_consolidado_cotizacion";
    private $table_contenedor_cotizacion_crons = "contenedor_consolidado_cotizacion_crons";
    private $table_contenedor_cotizacion_proveedores = "contenedor_consolidado_cotizacion_proveedores";
    private $table_contenedor_documentacion_files = "contenedor_consolidado_documentacion_files";
    private $table_contenedor_documentacion_folders = "contenedor_consolidado_documentacion_folders";
    private $table_contenedor_tipo_cliente = "contenedor_consolidado_tipo_cliente";
    private $table_contenedor_cotizacion_documentacion = "contenedor_consolidado_cotizacion_documentacion";
    private $table_contenedor_almacen_documentacion = "contenedor_consolidado_almacen_documentacion";
    private $table_contenedor_almacen_inspection = "contenedor_consolidado_almacen_inspection";
    private $table_conteneodr_proveedor_estados_tracking = "contenedor_proveedor_estados_tracking";
    private $roleCotizador = "Cotizador";
    private $roleCoordinacion = "Coordinación";
    private $roleContenedorAlmacen = "ContenedorAlmacen";
    private $roleCatalogoChina = "CatalogoChina";
    private $rolesChina = ["CatalogoChina", "ContenedorAlmacen"];
    private $roleDocumentacion = "Documentacion";
    private $aNewContainer = "new-container";
    private $aNewConfirmado = "new-confirmado";
    private $aNewCotizacion = "new-cotizacion";
    private $cambioEstadoProveedor = "cambio-estado-proveedor";
    private $table_contenedor_cotizacion_final = "contenedor_consolidado_cotizacion_final";
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

            $rol = $user->getNombreGrupo();

            $estadoChina = $request->estado_china ?? 'todos';
            $search = $request->search ?? '';
            // Compatibilidad con ambos nombres de parámetros
            $page = $request->input('page', $request->input('currentPage', 1));
            $perPage = $request->input('limit', $request->input('itemsPerPage', 100));
            $query = DB::table('contenedor_consolidado_cotizacion AS main')
                ->select([
                    'main.*',
                    'U.No_Nombres_Apellidos'
                ])
                ->leftJoin('contenedor_consolidado_tipo_cliente AS TC', 'TC.id', '=', 'main.id_tipo_cliente')
                ->leftJoin('usuario AS U', 'U.ID_Usuario', '=', 'main.id_usuario')
                ->where('main.id_contenedor', $idContenedor);

            if (!empty($search)) {
                Log::info('search: ' . $search);
                $query->where('main.nombre', 'LIKE', '%' . $search . '%');
            }
            if ($request->has('estado_coordinacion') || $request->has('estado_china')) {
                $query->whereExists(function ($sub) use ($request) {
                    $sub->select(DB::raw(1))
                        ->from('contenedor_consolidado_cotizacion_proveedores as proveedores')
                        ->whereRaw('proveedores.id_cotizacion = main.id');

                    // Usar OR en lugar de AND para que coincida con la lógica del CotizacionController
                    $sub->where(function ($q) use ($request) {
                        if ($request->has('estado_coordinacion') && $request->estado_coordinacion != 'todos') {
                            $q->where('proveedores.estados', $request->estado_coordinacion);
                        }
                        if ($request->has('estado_china') && $request->estado_china != 'todos') {
                            $q->orWhere('proveedores.estados_proveedor', $request->estado_china);
                        }
                    });
                });
            }
            if ($request->has('estado_cotizador') && $request->estado_cotizador != 'todos') {
                $query->where('main.estado_cotizador', $request->estado_cotizador);
            }


            switch ($rol) {
                case Usuario::ROL_COTIZADOR:
                    if ($user->getIdUsuario() != 28791 && $user->getIdUsuario() != 28911) {
                        $query->where('main.id_usuario', $user->getIdUsuario());
                    }

                    break;

                case Usuario::ROL_DOCUMENTACION:
                    $query->where('main.estado_cotizador', 'CONFIRMADO');
                    break;

                case Usuario::ROL_COORDINACION:
                    $query->where('main.estado_cotizador', 'CONFIRMADO');
                    break;
                case Usuario::ROL_ALMACEN_CHINA:
                    $query->where('main.estado_cotizador', 'CONFIRMADO');
                    break;
            }

            // Aplicar filtro whereNull después de los filtros de rol, igual que en CotizacionController
            $query->whereNull('main.id_cliente_importacion');
            $query->orderBy('main.id', 'asc');
            Log::info($query->toSql());
            // Ejecutar consulta con paginación
            $data = $query->paginate($perPage, ['*'], 'page', $page);
            $estadoChina = $request->estado_china;


            // Procesar datos para el frontend
            $dataProcessed = collect($data->items())->map(function ($item) use ($user, $estadoChina, $rol, $search) {
                // Obtener proveedores por separado para garantizar que siempre sea un array
                $proveedoresQuery = DB::table('contenedor_consolidado_cotizacion_proveedores')
                    ->where('id_cotizacion', $item->id)
                    ->select([
                        'id',
                        'qty_box',
                        'peso',
                        'id_cotizacion',
                        'cbm_total',
                        'supplier',
                        'code_supplier',
                        'estados_proveedor',
                        'estados',
                        'supplier_phone',
                        'cbm_total_china',
                        'qty_box_china',
                        'products',
                        'estado_china',
                        'arrive_date_china',
                        'arrive_date',
                        'send_rotulado_status',
                        'tipo_rotulado'
                    ])
                    ->get()
                    ->toArray();

                // Convertir a array asociativo y agregar id_proveedor
                $proveedores = array_map(function ($proveedor) {
                    $proveedorArray = (array)$proveedor;
                    $proveedorArray['id_proveedor'] = $proveedorArray['id'];
                    return $proveedorArray;
                }, $proveedoresQuery);

                // Forzar que siempre sea un array indexado numéricamente
                $proveedores = array_values($proveedores);

                // Filtrar proveedores por estado_china si es necesario
                if ($rol == Usuario::ROL_ALMACEN_CHINA && $estadoChina != "todos") {
                    $proveedores = array_filter($proveedores, function ($proveedor) use ($estadoChina) {
                        return ($proveedor['estados_proveedor'] ?? '') === $estadoChina;
                    });
                    // Reindexar después del filtro para mantener índices secuenciales
                    $proveedores = array_values($proveedores);
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
                    'id_contenedor_pago' => $item->id_contenedor_pago,
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
    public function getContenedorCotizacionProveedoresByUuid($uuid)
    {
        try {
            Log::info('uuid: ' . $uuid);
            // Buscar la cotización por UUID
            $cotizacion = DB::table('contenedor_consolidado_cotizacion AS main')
                ->select([
                    'main.*',
                    'U.No_Nombres_Apellidos'
                ])
                ->leftJoin('contenedor_consolidado_tipo_cliente AS TC', 'TC.id', '=', 'main.id_tipo_cliente')
                ->leftJoin('usuario AS U', 'U.ID_Usuario', '=', 'main.id_usuario')
                ->where('main.uuid', $uuid)
                ->first();

            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ], 404);
            }

            // Obtener proveedores de la cotización
            $proveedoresQuery = DB::table('contenedor_consolidado_cotizacion_proveedores')
                ->where('id_cotizacion', $cotizacion->id)
                ->select([
                    'id',
                    'qty_box',
                    'peso',
                    'id_cotizacion',
                    'cbm_total',
                    'supplier',
                    'code_supplier',
                    'estados_proveedor',
                    'estados',
                    'supplier_phone',
                    'cbm_total_china',
                    'qty_box_china',
                    'products',
                    'estado_china',
                    'arrive_date_china',
                    'arrive_date',
                    'send_rotulado_status'
                ])
                ->get()
                ->toArray();

            // Convertir a array asociativo y agregar id_proveedor
            $proveedores = array_map(function ($proveedor) {
                $proveedorArray = (array)$proveedor;
                $proveedorArray['id_proveedor'] = $proveedorArray['id'];
                return $proveedorArray;
            }, $proveedoresQuery);

            // Forzar que siempre sea un array indexado numéricamente
            $proveedores = array_values($proveedores);

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

            $dataProcessed = [
                'id' => $cotizacion->id,
                'id_contenedor' => $cotizacion->id_contenedor,
                'id_usuario' => $cotizacion->id_usuario,
                'id_contenedor_pago' => $cotizacion->id_contenedor_pago,
                'id_tipo_cliente' => $cotizacion->id_tipo_cliente,
                'nombre' => $cotizacion->nombre,
                'telefono' => $cotizacion->telefono,
                'estado_cotizador' => $cotizacion->estado_cotizador,
                'fecha_confirmacion' => $cotizacion->fecha_confirmacion,
                'No_Nombres_Apellidos' => $cotizacion->No_Nombres_Apellidos,
                'uuid' => $cotizacion->uuid,
                'proveedores' => $proveedores,
                'totales' => [
                    'cbm_total_china' => $cbmTotalChina,
                    'cbm_total_peru' => $cbmTotalPeru
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $dataProcessed
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cotización por UUID',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateContenedorCotizacionProveedoresByUuid($uuid, Request $request)
    {
        
        try {
            $cotizacion = DB::table('contenedor_consolidado_cotizacion')
                ->where('uuid', $uuid)
                ->first();
            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ], 404);
            }

            // Obtener todos los proveedores de la cotización para verificar el estado
            $todosProveedores = DB::table('contenedor_consolidado_cotizacion_proveedores')
                ->where('id_cotizacion', $cotizacion->id)
                ->get();

            $proveedoresActualizados = [];
            $proveedoresPendientes = [];

            foreach ($request->proveedores as $proveedorData) {
                $proveedor = DB::table('contenedor_consolidado_cotizacion_proveedores')
                    ->where('id', $proveedorData['id'])
                    ->where('id_cotizacion', $cotizacion->id)
                    ->first();
                if (!$proveedor) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Proveedor no encontrado'
                    ], 404);
                }

                // Actualizar usando update() en lugar de save() para consultas de tabla
                DB::table('contenedor_consolidado_cotizacion_proveedores')
                    ->where('id', $proveedor->id)
                    ->update([
                        'supplier_phone' => $proveedorData['supplier_phone'],
                        'supplier' => $proveedorData['supplier'],
                        'estados' => $this->STATUS_DATOS_PROVEEDOR,
                        'estados_proveedor' => $this->STATUS_NOT_CONTACTED
                    ]);

                // Actualizar tracking siguiendo el patrón correcto
                $ahora = now();
                
                // Obtener el registro más reciente del tracking
                $trackingActual = DB::table($this->table_conteneodr_proveedor_estados_tracking)
                    ->where('id_proveedor', $proveedor->id)
                    ->where('id_cotizacion', $cotizacion->id)
                    ->orderBy('created_at', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($trackingActual) {
                    // Actualizar el registro existente con updated_at
                    DB::table($this->table_conteneodr_proveedor_estados_tracking)
                        ->where('id', $trackingActual->id)
                        ->update(['updated_at' => $ahora]);
                }

                // Insertar nuevo registro con el estado DATOS PROVEEDOR
                DB::table($this->table_conteneodr_proveedor_estados_tracking)
                    ->insert([
                        'id_proveedor' => $proveedor->id,
                        'id_cotizacion' => $cotizacion->id,
                        'estado' => $this->STATUS_DATOS_PROVEEDOR,
                        'created_at' => $ahora,
                        'updated_at' => $ahora
                    ]);

                $proveedoresActualizados[] = $proveedor->id;
            }

            // Verificar qué proveedores quedan pendientes DESPUÉS de actualizar
            // Necesitamos obtener los proveedores actualizados de la base de datos
            $proveedoresActualizadosDB = DB::table('contenedor_consolidado_cotizacion_proveedores')
                ->where('id_cotizacion', $cotizacion->id)
                ->get();

            foreach ($proveedoresActualizadosDB as $proveedor) {
                if ($proveedor->estados !== $this->STATUS_DATOS_PROVEEDOR) {
                    $proveedoresPendientes[] = [
                        'id' => $proveedor->id,
                        'code_supplier' => $proveedor->code_supplier
                    ];
                }
            }

            // Preparar mensaje de WhatsApp
            $telefono = $this->formatPhoneNumber($cotizacion->telefono);
            $mensaje = "";
            $tipoMensaje = "";

            if (count($proveedoresPendientes) > 0) {
                // Guardar1: Hay proveedores pendientes
                $mensaje = "Se registró exitosamente los datos de tu proveedor.\n";
                $mensaje .= "Queda pendiente los datos del proveedor:\n";

                foreach ($proveedoresPendientes as $pendiente) {
                    $mensaje .= "• #" . $pendiente['code_supplier'] . "\n";
                    $mensaje .= "----------------------------------------------------------\n";
                }
                $mensaje .= "\nContacta al vendedor y sube los datos faltantes.";
                // en la siguiente url: https://datosprovedor.probusiness.pe/uuid
                $url = env('APP_URL_DATOS_PROVEEDOR') . '/' . $uuid;

                $mensaje .= "\nIngresar aquí: " . $url;
                $tipoMensaje = "guardar1";
            } else {
                // Guardar2: Todos los proveedores completos
                $mensaje = "Se registró exitosamente los datos de tu proveedor.\n";
                $mensaje .= "Gracias por ayudarnos a hacer mejor nuestro trabajo, el equipo de China se contactará pronto con tu proveedor.";

                $tipoMensaje = "guardar2";
            }

            // Enviar mensaje de WhatsApp
            $resultadoWhatsApp = $this->sendMessage($mensaje, $telefono);

            Log::info('Mensaje de WhatsApp enviado para actualización de proveedores', [
                'cotizacion_id' => $cotizacion->id,
                'uuid' => $uuid,
                'telefono' => $telefono,
                'tipo_mensaje' => $tipoMensaje,
                'proveedores_actualizados' => count($proveedoresActualizados),
                'proveedores_pendientes' => count($proveedoresPendientes),
                'resultado_whatsapp' => $resultadoWhatsApp
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Proveedores actualizados correctamente',
                'popup_message' => $mensaje,
                'tipo_mensaje' => $tipoMensaje,
                'proveedores_actualizados' => count($proveedoresActualizados),
                'proveedores_pendientes' => count($proveedoresPendientes),
                'whatsapp_enviado' => $resultadoWhatsApp['success'] ?? false
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar proveedores',
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
    public function updateEstadoCotizacionProveedor(Request $request)
    {
        try {
            $idProveedor = $request->id;
            $idCotizacion = CotizacionProveedor::where('id', $idProveedor)->first()->id_cotizacion;
            $idContenedor = Cotizacion::where('id', $idCotizacion)->first()->id_contenedor;
            $estado = $request->estado;

            if (in_array($estado, ["ROTULADO", "RESERVADO", 'COBRANDO'])) {
                DB::table($this->table_contenedor_cotizacion_proveedores)
                    ->where('id_cotizacion', $idCotizacion)
                    ->where(function ($query) {
                        $query->whereNull('estados')
                            ->orWhereIn('estados', [
                                'RESERVADO',
                                'ROTULADO',
                                'COBRANDO',
                                'DATOS PROVEEDOR',
                                'INSPECCIONADO'
                            ]);
                    })
                    ->update(['estados' => $estado]);

                // Actualización específica para el proveedor
                DB::table($this->table_contenedor_cotizacion_proveedores)
                    ->where('id_cotizacion', $idCotizacion)
                    ->where('id', $idProveedor)
                    ->update(['estados' => $estado]);
            }
            // Manejo del estado LOADED
            else if ($estado == "LOADED") {
                // Verificar estado RESERVADO en tracking
                $estadoReservado = DB::table($this->table_conteneodr_proveedor_estados_tracking)
                    ->where('id_cotizacion', $idCotizacion)
                    ->where('estado', 'RESERVADO')
                    ->exists();

                $estadoCliente = $estadoReservado ? "RESERVADO" : "NO RESERVADO";

                // Actualizar estado_cliente en cotización
                DB::table($this->table_contenedor_cotizacion)
                    ->where('id', $idCotizacion)
                    ->update(['estado_cliente' => $estadoCliente]);

                // Actualizar estado del proveedor
                DB::table($this->table_contenedor_cotizacion_proveedores)
                    ->where('id_cotizacion', $idCotizacion)
                    ->where('id', $idProveedor)
                    ->update(['estados_proveedor' => "LOADED"]);

                // Calcular y actualizar volumen_china
                $volumenChina = DB::table($this->table_contenedor_cotizacion_proveedores)
                    ->where('id_cotizacion', $idCotizacion)
                    ->where('estados_proveedor', "LOADED")
                    ->sum(DB::raw('IFNULL(cbm_total_china, 0)'));

                DB::table($this->table_contenedor_cotizacion)
                    ->where('id', $idCotizacion)
                    ->update(['volumen_china' => $volumenChina]);
            }
            // Manejo de estados específicos
            else if (in_array($estado, ["NC", "C", "R", "NS", "NO LOADED", "INSPECTION", 'WAIT'])) {
                DB::table($this->table_contenedor_cotizacion_proveedores)
                    ->where('id_cotizacion', $idCotizacion)
                    ->where('id', $idProveedor)
                    ->update(['estados_proveedor' => $estado]);
            }
            // Manejo de otros estados
            else {
                DB::table($this->table_contenedor_cotizacion_proveedores)
                    ->where('id_cotizacion', $idCotizacion)
                    ->where('id', $idProveedor)
                    ->update(['estados' => $estado]);
            }

            // Actualizar tracking siguiendo el patrón correcto
            $ahora = now();
            
            // Obtener el registro más reciente del tracking
            $trackingActual = DB::table($this->table_conteneodr_proveedor_estados_tracking)
                ->where('id_proveedor', $idProveedor)
                ->where('id_cotizacion', $idCotizacion)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($trackingActual) {
                // Actualizar el registro existente con updated_at
                DB::table($this->table_conteneodr_proveedor_estados_tracking)
                    ->where('id', $trackingActual->id)
                    ->update(['updated_at' => $ahora]);
            }

            // Insertar nuevo registro con el nuevo estado
            DB::table($this->table_conteneodr_proveedor_estados_tracking)
                ->insert([
                    'id_proveedor' => $idProveedor,
                    'id_cotizacion' => $idCotizacion,
                    'estado' => $estado,
                    'created_at' => $ahora,
                    'updated_at' => $ahora
                ]);



            // Verificar estado RESERVADO y actualizar estado_cliente
            $estadoReservado = DB::table($this->table_conteneodr_proveedor_estados_tracking)
                ->where('id_cotizacion', $idCotizacion)
                ->where('estado', 'RESERVADO')
                ->exists();

            $estadoCliente = $estadoReservado ? "RESERVADO" : "NO RESERVADO";

            DB::table($this->table_contenedor_cotizacion)
                ->where('id', $idCotizacion)
                ->update(['estado_cliente' => $estadoCliente]);

            // Llamada al manejador de actualización de cotización
            $data = $this->handlerUpdateCotizacionProveedor($estado, $idProveedor, $idCotizacion);

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado correctamente',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error en updateEstadoCotizacionProveedor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function handlerUpdateCotizacionProveedor($estado, $idProveedor, $idCotizacion)
    {
        try {
            // Obtener información básica de la cotización
            $cotizacionInfo = DB::table($this->table_contenedor_cotizacion)
                ->where('id', $idCotizacion)
                ->select('nombre', 'id_contenedor', 'telefono')
                ->first();

            if (!$cotizacionInfo) {
                throw new \Exception("No se encontró la cotización especificada");
            }

            $cliente = $cotizacionInfo->nombre;
            $idContenedor = $cotizacionInfo->id_contenedor;
            $telefono = preg_replace('/\s+/', '', $cotizacionInfo->telefono);
            $this->phoneNumberId = $telefono ? $telefono . '@c.us' : '';

            // Obtener proveedores asociados a la cotización
            $proveedores = DB::table($this->table_contenedor_cotizacion_proveedores)
                ->where('id_cotizacion', $idCotizacion)
                ->select('code_supplier', 'products', 'send_rotulado_status', 'id')
                ->get()
                ->toArray();

            if (empty($proveedores)) {
                throw new \Exception("No se encontraron proveedores para esta cotización");
            }

            // Obtener información del contenedor
            $contenedorInfo = DB::table('carga_consolidada_contenedor')
                ->where('id', $idContenedor)
                ->select('carga')
                ->first();

            if (!$contenedorInfo) {
                throw new \Exception("No se encontró información del contenedor");
            }

            $carga = $contenedorInfo->carga;

            if ($estado == "ROTULADO") {
                return $this->procesarEstadoRotulado($cliente, $carga, $proveedores, $idCotizacion);
            } elseif ($estado == "COBRANDO") {
                return $this->procesarEstadoCobrando($idProveedor, $idCotizacion, $carga);
            }

            return "success";
        } catch (\Exception $e) {
            Log::error("Error en handlerUpdateCotizacionProveedor: " . $e->getMessage());
            return ['status' => "error", 'message' => $e->getMessage()];
        }
    }
    /**
     * Procesar estado de rotulado para proveedores
     *
     * @param string $cliente
     * @param string $carga
     * @param array $proveedores
     * @param int $idCotizacion
     * @return \Illuminate\Http\Response
     * @throws Exception
     */
    protected function procesarEstadoRotulado($cliente, $carga, $proveedores, $idCotizacion)
    {
        DB::beginTransaction();
        try {
            // Asegurar que $proveedores sea un array
            $proveedores = collect($proveedores)->map(function ($proveedor) {
                // Convertir objetos stdClass a arrays
                return is_object($proveedor) ? (array) $proveedor : $proveedor;
            })->toArray();

            Log::info($proveedores);

            // Procesar plantilla de bienvenida
            $htmlWelcomePath = public_path('assets/templates/Welcome_Consolidado_Template.html');
            if (!file_exists($htmlWelcomePath)) {
                throw new Exception("No se encontró la plantilla de bienvenida");
            }

            $htmlWelcomeContent = file_get_contents($htmlWelcomePath);
            $htmlWelcomeContent = mb_convert_encoding($htmlWelcomeContent, 'UTF-8', mb_detect_encoding($htmlWelcomeContent));
            $htmlWelcomeContent = str_replace('{{consolidadoNumber}}', $carga, $htmlWelcomeContent);

            // Filtrar proveedores
            $providersHasSended = array_filter($proveedores, function ($proveedor) {
                return ($proveedor['send_rotulado_status'] ?? '') === 'SENDED';
            });
            $providersHasNoSended = array_filter($proveedores, function ($proveedor) {
                return ($proveedor['send_rotulado_status'] ?? '') === 'PENDING';
            });

            if (empty($providersHasNoSended)) {
                throw new Exception("No hay proveedores pendientes de envío");
            }

            // Enviar mensaje de bienvenida si es necesario
            if (count($providersHasSended) == 0) {
                $this->sendWelcome($carga);
            } elseif (count($providersHasSended) > 0 && count($providersHasNoSended) > 0) {
                $this->sendMessage("Hola 🙋🏻‍♀, te escribe el área de coordinación de Probusiness. 
        
📢 Añadiste un nuevo proveedor en el *Consolidado #${carga}*

*Rotulado: 👇🏼*  
Tienes que indicarle a tu proveedor que las cajas máster 📦 cuenten con un rotulado para 
identificar tus paquetes y diferenciarlas de los demás cuando llegue a nuestro almacén.");
            }

            // Configurar ZIP
            $zipFileName = storage_path('app/Rotulado.zip');
            $zipDirectory = dirname($zipFileName);

            Log::info('Configurando ZIP: ' . $zipFileName);

            // Asegurar que el directorio existe
            if (!is_dir($zipDirectory)) {
                mkdir($zipDirectory, 0755, true);
                Log::info('Directorio creado: ' . $zipDirectory);
            }

            // Eliminar archivo ZIP existente si existe
            if (file_exists($zipFileName)) {
                unlink($zipFileName);
                Log::info('ZIP anterior eliminado');
            }

            $zip = new ZipArchive();
            $zipResult = $zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($zipResult !== TRUE) {
                Log::error('No se pudo crear el archivo ZIP. Código de error: ' . $zipResult);
                throw new Exception("No se pudo crear el archivo ZIP. Código: $zipResult");
            }

            Log::info('ZIP creado correctamente');

            // Configuración de DomPDF
            $options = new Options();
            $options->set('isHtml5ParserEnabled', false);
            $options->set('isFontSubsettingEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('isPhpEnabled', true);
            $options->set('chroot', public_path());
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('defaultMediaType', 'screen');
            $options->set('isFontSubsettingEnabled', false);
            $options->set('debugKeepTemp', false);
            $options->set('debugCss', false);
            $options->set('debugLayout', false);
            $options->set('debugLayoutLines', false);
            $options->set('debugLayoutBlocks', false);
            $options->set('debugLayoutInline', false);
            $options->set('debugLayoutPaddingBox', false);
            $sleepSendMedia = 7;

            $processedProviders = 0;

            // Procesar cada proveedor pendiente
            foreach ($providersHasNoSended as $proveedor) {
                Log::info('Procesando proveedor: ' . json_encode($proveedor));
                $supplierCode = $proveedor['code_supplier'] ?? '';
                $products = $proveedor['products'] ?? '';
                $sleepSendMedia += 1;

                // Procesar plantilla de rotulado
                $htmlFilePath = public_path('assets/templates/Rotulado_Template.html');
                if (!file_exists($htmlFilePath)) {
                    Log::error('No se encontró plantilla de rotulado: ' . $htmlFilePath);
                    throw new Exception("No se encontró la plantilla de rotulado");
                }

                $htmlContent = file_get_contents($htmlFilePath);
                $htmlContent = mb_convert_encoding($htmlContent, 'UTF-8', mb_detect_encoding($htmlContent));

                // Convertir imagen a base64
                $headerImagePath = public_path('assets/templates/ROTULADO_HEADER.png');
                $headerImageBase64 = '';
                if (file_exists($headerImagePath)) {
                    $imageData = file_get_contents($headerImagePath);
                    $headerImageBase64 = 'data:image/png;base64,' . base64_encode($imageData);
                    Log::info('Imagen convertida a base64 exitosamente');
                } else {
                    Log::error('No se encontró la imagen header: ' . $headerImagePath);
                }

                $htmlContent = str_replace('{{cliente}}', $cliente, $htmlContent);
                $htmlContent = str_replace('{{supplier_code}}', $supplierCode, $htmlContent);
                $htmlContent = str_replace('{{carga}}', $carga, $htmlContent);
                $htmlContent = str_replace('{{base_url}}/assets/templates/ROTULADO_HEADER.png', $headerImageBase64, $htmlContent);

                Log::info('HTML procesado para proveedor: ' . $supplierCode);

                // Generar PDF
                try {
                    Log::info('Iniciando generación de PDF para proveedor: ' . $supplierCode);

                    $dompdf = new Dompdf($options);
                    $dompdf->loadHtml($htmlContent);
                    $dompdf->setPaper('A4', 'portrait');

                    Log::info('PDF configurado, iniciando render...');
                    $dompdf->render();

                    Log::info('Render completado, obteniendo output...');
                    $pdfContent = $dompdf->output();

                    Log::info('PDF generado exitosamente');
                } catch (Exception $pdfException) {
                    Log::error('Error generando PDF: ' . $pdfException->getMessage());
                    Log::error('Stack trace: ' . $pdfException->getTraceAsString());
                    throw new Exception('Error generando PDF para proveedor ' . $supplierCode . ': ' . $pdfException->getMessage());
                }

                Log::info('PDF generado para proveedor: ' . $supplierCode . ', tamaño: ' . strlen($pdfContent));

                // Guardar temporalmente
                $tempFilePath = storage_path("app/temp_document_proveedor{$supplierCode}.pdf");
                if (file_exists($tempFilePath)) {
                    unlink($tempFilePath);
                }

                if (file_put_contents($tempFilePath, $pdfContent) === false) {
                    Log::error('No se pudo guardar PDF temporal: ' . $tempFilePath);
                    throw new Exception("No se pudo guardar el PDF temporal");
                }

                Log::info('PDF guardado temporalmente: ' . $tempFilePath);

                try {
                    if (!$zip->addFile($tempFilePath, "Rotulado_{$supplierCode}.pdf")) {
                        Log::error("No se pudo añadir $tempFilePath al ZIP");
                        continue;
                    }

                    Log::info("Archivo añadido al ZIP: Rotulado_{$supplierCode}.pdf");

                    // Enviar documento al proveedor
                    $this->sendDataItem(
                        "Producto: {$products}\nCódigo de proveedor: {$supplierCode}",
                        $tempFilePath
                    );

                    Log::info('Documento enviado por WhatsApp para proveedor: ' . $supplierCode);

                    // Actualizar estado del proveedor
                    CotizacionProveedor::where('id', $proveedorArray['id'] ?? null)
                        ->update(["send_rotulado_status" => "SENDED"]);

                    Log::info('Estado actualizado para proveedor: ' . $supplierCode);

                    $processedProviders++;
                } catch (Exception $e) {
                    Log::error('Error procesando proveedor ' . $supplierCode . ': ' . $e->getMessage());
                    continue;
                } finally {
                    // Limpiar memoria
                    gc_collect_cycles();
                }
            }

            Log::info("Total de proveedores procesados: $processedProviders");

            // Cerrar ZIP
            if (!$zip->close()) {
                Log::error("Error al cerrar el archivo ZIP");
                throw new Exception("Error al cerrar el archivo ZIP");
            }

            Log::info('ZIP cerrado correctamente');

            // Enviar imagen de dirección
            $direccionUrl = public_path('assets/images/Direccion.jpg');
            $sleepSendMedia += 3;
            $this->sendMedia($direccionUrl, 'image/jpg', '🏽Dile a tu proveedor que envíe la carga a nuestro almacén en China', null, $sleepSendMedia);

            // Enviar mensaje adicional
            $sleepSendMedia += 3;
            $this->sendMessage("También necesito los datos de tu proveedor para comunicarnos y recibir tu carga.

➡ *Datos del proveedor: (Usted lo llena)*

☑ Nombre del producto:
☑ Nombre del vendedor:
☑ Celular del vendedor:

Te avisaré apenas tu carga llegue a nuestro almacén de China, cualquier duda me escribes. 🫡", null, $sleepSendMedia);

            // Verificar que el ZIP se generó correctamente
            if (!file_exists($zipFileName)) {
                Log::error("El archivo ZIP no existe después de cerrarlo: $zipFileName");
                throw new Exception("El archivo ZIP no se generó correctamente");
            }

            $fileSize = filesize($zipFileName);
            Log::info("Tamaño del ZIP generado: $fileSize bytes");

            if ($fileSize === false || $fileSize == 0) {
                Log::error("El archivo ZIP está vacío o no se puede leer");
                throw new Exception("El archivo ZIP está vacío");
            }

            DB::commit();
            return response()->download($zipFileName, 'Rotulado.zip');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error en procesarEstadoRotulado: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Procesar estado de rotulado usando Job asíncrono
     *
     * @param string $cliente
     * @param string $carga
     * @param array $proveedores
     * @param int $idCotizacion
     * @return \Illuminate\Http\Response
     * @throws Exception
     */
    protected function procesarEstadoRotuladoJob($cliente, $carga, $proveedores, $idCotizacion, $total_movilidad_personal = 0)
    {
        try {
            $idContenedor = Cotizacion::where('id', $idCotizacion)->first()->id_contenedor;
            $carga = Contenedor::where('id', $idContenedor)->first()->carga;

            // Dispatch del Job para procesamiento asíncrono
            SendRotuladoJob::dispatch($cliente, $carga, $proveedores, $idCotizacion, $total_movilidad_personal)->onQueue('importaciones');

            Log::info('SendRotuladoJob dispatchado exitosamente');

            return response()->json([
                'success' => true,
                'message' => 'Procesamiento de rotulado iniciado',
                'data' => [
                    'cliente' => $cliente,
                    'carga' => $carga,
                    'proveedores_count' => count($proveedores)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en procesarEstadoRotuladoJob: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar rotulado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesar estado de cobranza para proveedor
     *
     * @param int $idProveedor
     * @param int $idCotizacion
     * @param string $carga
     * @return string
     * @throws Exception
     */
    protected function procesarEstadoCobrando($idProveedor, $idCotizacion, $carga)
    {
        try {
            // Obtener información del proveedor
            $proveedorInfo = CotizacionProveedor::findOrFail($idProveedor);

            $supplierCode = $proveedorInfo->code_supplier;
            $idCotizacion = $proveedorInfo->id_cotizacion;

            // Obtener información de la cotización
            $cotizacionInfo = Cotizacion::findOrFail($idCotizacion);

            $volumen = $cotizacionInfo->volumen;
            $valorCot = $cotizacionInfo->monto;
            $idContenedor = $cotizacionInfo->id_contenedor;

            // Obtener fecha de cierre
            $contenedor = CargaConsolidadaContenedor::findOrFail($idContenedor);
            $fechaCierre = $contenedor->f_cierre;

            $fCierre = \Carbon\Carbon::parse($fechaCierre)->locale('es')->format('d F');
            $meses = [
                'January' => 'Enero',
                'February' => 'Febrero',
                'March' => 'Marzo',
                'April' => 'Abril',
                'May' => 'Mayo',
                'June' => 'Junio',
                'July' => 'Julio',
                'August' => 'Agosto',
                'September' => 'Septiembre',
                'October' => 'Octubre',
                'November' => 'Noviembre',
                'December' => 'Diciembre'
            ];
            $fCierre = strtr($fCierre, $meses);

            // Obtener información del cliente
            $clienteInfo = Cotizacion::findOrFail($idCotizacion);

            $telefono = preg_replace('/\s+/', '', $clienteInfo->telefono);
            $phoneNumberId = $telefono ? $telefono . '@c.us' : '';

            // Construir y enviar mensaje
            $message = "Reserva de espacio:\n" .
                "*Consolidado #" . $carga . "-2025*\n\n" .
                "Ahora tienes que hacer el pago del CBM preliminar para poder subir su carga en nuestro contenedor.\n\n" .
                "☑ CBM Preliminar: " . $volumen . " cbm\n" .
                "☑ Costo CBM: $" . $valorCot . "\n" .
                "☑ Fecha Limite de pago: " . $fCierre . "\n\n" .
                "⚠ Nota: Realizar el pago antes del llenado del contenedor.\n\n" .
                "📦 En caso hubiera variaciones en el cubicaje se cobrará la diferencia en la cotización final.\n\n" .
                "Apenas haga el pago, envíe por este medio para hacer la reserva.";

            $this->sendMessage($message);

            // Enviar imagen de pagos
            $pagosUrl = public_path('assets/images/pagos-full.jpg');
            $this->sendMedia($pagosUrl, 'image/jpg');

            return "success";
        } catch (Exception $e) {
            Log::error('Error en procesarEstadoCobrando: ' . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Actualizar datos del proveedor
     */
    public function updateProveedorData(Request $request)
    {
        try {
            $idProveedor = $request->id;
            $data = $request->all();
            $user = JWTAuth::parseToken()->authenticate();
            Log::info('user: ' . json_encode($user));
            Log::info('role: ' . $user->getNombreGrupo());
            //LOG DATA
            Log::info('data: ' . json_encode($data));
            $proveedor = CotizacionProveedor::where('id', $idProveedor)
                ->first();

            if (!$proveedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado'
                ], 404);
            }
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }
            $estado = $proveedor->estados;
            $estadoProveedor = $proveedor->estados_proveedor;
            $idContenedor = $proveedor->id_contenedor;
            $idCotizacion = $proveedor->id_cotizacion;
            $supplierCode = $proveedor->code_supplier;

            if ((isset($data['supplier_phone']) || isset($data['supplier']))) {
                $statusToUpdate = $this->providerCoordinacionOrderStatus[$this->STATUS_DATOS_PROVEEDOR] ?? 0;
                $estadoProveedorOrder = $this->providerCoordinacionOrderStatus[$estadoProveedor] ?? 0;
                if ($estadoProveedorOrder < $statusToUpdate) {
                    $proveedor->estados = $this->STATUS_DATOS_PROVEEDOR;
                    $proveedor->estados_proveedor = $this->STATUS_NOT_CONTACTED;
                    $proveedor->supplier_phone = $data['supplier_phone'] ?? null;
                    $proveedor->supplier = $data['supplier'] ?? null;
                    $proveedor->save();

                    // Actualizar tracking siguiendo el patrón correcto
                    $ahora = now();
                    
                    // Obtener el registro más reciente del tracking
                    $trackingActual = DB::table($this->table_conteneodr_proveedor_estados_tracking)
                        ->where('id_proveedor', $idProveedor)
                        ->where('id_cotizacion', $idCotizacion)
                        ->orderBy('created_at', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($trackingActual) {
                        // Actualizar el registro existente con updated_at
                        DB::table($this->table_conteneodr_proveedor_estados_tracking)
                            ->where('id', $trackingActual->id)
                            ->update(['updated_at' => $ahora]);
                    }

                    // Insertar nuevo registro con el estado DATOS PROVEEDOR
                    DB::table($this->table_conteneodr_proveedor_estados_tracking)
                        ->insert([
                            'id_proveedor' => $idProveedor,
                            'id_cotizacion' => $idCotizacion,
                            'estado' => $this->STATUS_DATOS_PROVEEDOR,
                            'created_at' => $ahora,
                            'updated_at' => $ahora
                        ]);
                }


                $this->verifyContainerIsCompleted($idContenedor);
            }
            if ((isset($data['code_supplier']))) {
                $proveedor->code_supplier = $data['code_supplier'];
                $proveedor->save();
            }
            if (
                isset($data['arrive_date_china']) &&
                (!isset($data['qty_box_china']) && !isset($data['cbm_total_china']))
                && $user->getNombreGrupo() == Usuario::ROL_ALMACEN_CHINA
            ) {
                $data['arrive_date_china'] = date('Y-m-d', strtotime(str_replace('/', '-', $data['arrive_date_china'])));
                $estadoProveedorOrder = $this->providerOrderStatus[$estadoProveedor] ?? 0;
                $estadoProvedorToUpdate = $this->providerOrderStatus[$this->STATUS_CONTACTED] ?? 0;
                if ($estadoProveedorOrder < $estadoProvedorToUpdate) {

                    if (\DateTime::createFromFormat('Y-m-d', $data['arrive_date_china']) !== false) {
                        $proveedor->arrive_date_china = $data['arrive_date_china'];
                    } else {
                        Log::error('Error en updateProveedorData: La fecha de llegada de china no es válida');
                    }


                    //INSERT INTO TABLE contenedor_proveedor_estados_tracking with estado CONTACTED and id_cotizacion and id_proveedor
                    $proveedor->estados_proveedor = $this->STATUS_CONTACTED;
                    $proveedor->save();

                    //conver yyyy-mm-dd to dd de Mes
                    $date = \DateTime::createFromFormat('Y-m-d', $data['arrive_date_china']);
                    $month = $date->format('F');
                    $day = $date->format('d');
                    $months = [
                        'January' => 'Enero',
                        'February' => 'Febrero',
                        'March' => 'Marzo',
                        'April' => 'Abril',
                        'May' => 'Mayo',
                        'June' => 'Junio',
                        'July' => 'Julio',
                        'August' => 'Agosto',
                        'September' => 'Septiembre',
                        'October' => 'Octubre',
                        'November' => 'Noviembre',
                        'December' => 'Diciembre'
                    ];
                    $month = strtr($month, $months);
                    $date = $day . ' de ' . $month;
                    $message = 'Hola, hemos contactado a tu proveedor con código ' .
                        $supplierCode . ' nos comunica que la carga será enviada el ' .
                        $date . '.';
                    $cotizacion = Cotizacion::find($idCotizacion);
                    $telefono = $cotizacion->telefono;

                    $telefono = preg_replace('/\s+/', '', $telefono);
                    $this->phoneNumberId = $telefono ? $telefono . '@c.us' : '';
                    $this->sendMessage($message);

                    // Disparar evento de proveedor contactado en China
                    try {
                        $carga = Contenedor::where('id', $idContenedor)->first()->carga;
                        //china contacto al proveedor con codigo de proveedor "codigo"del cliente "nombre" del contenedor "carga" y fecha de llegada "fecha" 
                        $message = "China contacto al proveedor con codigo de proveedor " . $supplierCode . " del cliente " . $cotizacion->nombre . " del contenedor " . $carga . " y fecha de llegada " . $data['arrive_date_china'];
                        CotizacionChinaContacted::dispatch($cotizacion, $proveedor, $supplierCode, $data['arrive_date_china'], $message);

                        // Crear notificaciones en la base de datos para Coordinación y Cotizador
                        $this->crearNotificacionesProveedorContactado($cotizacion, $proveedor, $supplierCode, $carga, $data['arrive_date_china'], $user);
                    } catch (\Exception $e) {
                        Log::error('Error al disparar evento CotizacionChinaContacted: ' . $e->getMessage());
                    }
                }
                $this->verifyContainerIsCompleted($idContenedor);
            }
            if (
                isset($data['qty_box_china']) && isset($data['cbm_total_china'])
                && $user->getNombreGrupo() == Usuario::ROL_ALMACEN_CHINA
            ) {

                if (!isset($data['arrive_date_china']) || $data['arrive_date_china'] == null) {
                    $data['arrive_date_china'] = \Carbon\Carbon::now()->format('Y-m-d');
                } else {
                    $data['arrive_date_china'] = \Carbon\Carbon::parse($data['arrive_date_china'])->format('Y-m-d');
                }
                $estadoProveedorOrder = $this->providerOrderStatus[$estadoProveedor] ?? 0;
                $estadoProvedorToUpdate = $this->providerOrderStatus[$this->STATUS_RECIVED] ?? 0;
                if ($estadoProveedorOrder < $estadoProvedorToUpdate) {
                    if (!is_numeric($data['qty_box_china']) || !is_numeric($data['cbm_total_china']) || $data['qty_box_china'] <= 0 || $data['cbm_total_china'] <= 0) {
                        Log::error('Error en updateProveedorData: La cantidad de cajas y volumen total de china deben ser números y mayores que 0');
                        $proveedor->estados_proveedor = $this->STATUS_CONTACTED;
                    } else {
                        $proveedor->qty_box_china = $data['qty_box_china'];
                        $proveedor->cbm_total_china = $data['cbm_total_china'];
                        $proveedor->estados_proveedor = $this->STATUS_RECIVED;
                    }
                    Log::info('proveedor->arrive_date_china: ' . $proveedor->arrive_date_china);
                    ///validate if proveedor has arrive_date_china and is valid date if not update
                    if (
                        !isset($proveedor->arrive_date_china) || $proveedor->arrive_date_china == null

                    ) {
                        //amd if is valid date
                        if (\DateTime::createFromFormat('Y-m-d', $data['arrive_date_china']) !== false) {
                            $proveedor->arrive_date_china = $data['arrive_date_china'];
                            try {
                                $carga = Contenedor::where('id', $idContenedor)->first()->carga;
                                $cotizacion = Cotizacion::find($idCotizacion);
                                $supplierCode = $proveedor->code_supplier;
                                $user = JWTAuth::parseToken()->authenticate();
                                //china contacto al proveedor con codigo de proveedor "codigo"del cliente "nombre" del contenedor "carga" y fecha de llegada "fecha" 
                                $message = "China contacto al proveedor con codigo de proveedor " . $supplierCode . " del cliente " . $cotizacion->nombre . " del contenedor " . $carga . " y fecha de llegada " . $data['arrive_date_china'];
                                CotizacionChinaContacted::dispatch($cotizacion, $proveedor, $supplierCode, $data['arrive_date_china'], $message);
                                $usuarioActual = JWTAuth::parseToken()->authenticate();
                                //if qty box china is greater than 0 and cbm total china is greater than 0, dispatch event received else contacted
                                if ($data['qty_box_china'] > 0 && $data['cbm_total_china'] > 0) {
                                    $this->crearNotificacionesProveedorRecibido($cotizacion, $proveedor, $supplierCode, $data['qty_box_china'], $data['cbm_total_china'], $carga, $usuarioActual);
                                    $this->dispararEventoYNotificacionProveedorRecibido($cotizacion, $proveedor, $supplierCode, $data['qty_box_china'], $data['cbm_total_china'], $carga, $usuarioActual);
                                } else {
                                    CotizacionChinaContacted::dispatch($cotizacion, $proveedor, $supplierCode, $data['arrive_date_china'], $message);
                                    $this->crearNotificacionesProveedorContactado($cotizacion, $proveedor, $supplierCode, $carga, $data['arrive_date_china'], $user);

                                }
                                // Crear notificaciones en la base de datos para Coordinación y Cotizador
                            } catch (\Exception $e) {
                                Log::error('Error al disparar evento CotizacionChinaContacted: ' . $e->getMessage());
                            }
                        } else {
                            Log::error('Error en updateProveedorData: La fecha de llegada de china no es válida');
                        }
                    }else{
                        $usuarioActual = JWTAuth::parseToken()->authenticate();
                        $cotizacion = Cotizacion::find($idCotizacion);
                        $supplierCode = $proveedor->code_supplier;
                        $carga = Contenedor::where('id', $idContenedor)->first()->carga;
                        $this->dispararEventoYNotificacionProveedorRecibido($cotizacion, $proveedor, $supplierCode, $data['qty_box_china'], $data['cbm_total_china'], $carga, $usuarioActual);
                        $this->crearNotificacionesProveedorRecibido($cotizacion, $proveedor, $supplierCode, $data['qty_box_china'], $data['cbm_total_china'], $carga, $usuarioActual);
                    }
                    $proveedor->save();



                    $usuariosAlmacen = $this->getUsersByGrupo(Usuario::ROL_COORDINACION);
                    $ids = array_column($usuariosAlmacen, 'ID_Usuario');
                    $message = "Se ha actualizado el proveedor con codigo de proveedor " . $supplierCode . " a estado RECIBIDO";
                } else {
                    $message = "Se ha actualizado la cantidad de cajas y volumen total de china del proveedor con codigo de proveedor " . $supplierCode . " a " . $data['qty_box_china'] . " cajas y " . $data['cbm_total_china'] . " m3";
                }
                $contenedorEstado = Cotizacion::where('id_contenedor', $idContenedor)->first()->estado_china;
                if ($contenedorEstado == "PENDIENTE") {
                    $cotizacion = Cotizacion::find($idContenedor);
                    $cotizacion->estado_china = "RECIBIENDO";
                    $cotizacion->estado = "RECIBIENDO";
                    $cotizacion->save();
                }
            }
            // Permitir al frontend enviar y actualizar los nuevos estados de documentos
            $allowedDocumentStatuses = ['Pendiente', 'Recibido', 'Observado', 'Revisado'];
            if (isset($data['invoice_status']) && in_array($data['invoice_status'], $allowedDocumentStatuses)) {
                $proveedor->invoice_status = $data['invoice_status'];
            }
            if (isset($data['packing_status']) && in_array($data['packing_status'], $allowedDocumentStatuses)) {
                $proveedor->packing_status = $data['packing_status'];
            }
            if (isset($data['excel_conf_status']) && in_array($data['excel_conf_status'], $allowedDocumentStatuses)) {
                $proveedor->excel_conf_status = $data['excel_conf_status'];
            }

            // Actualizaciones masivas restantes
            $proveedor->update($data);
            $volumenChina = CotizacionProveedor::where('id_cotizacion', $idCotizacion)
                ->where('estados_proveedor', "LOADED")
                ->sum('cbm_total_china');

            $cotizacion = Cotizacion::find($idCotizacion);
            $cotizacion->volumen_china = $volumenChina;
            $cotizacion->save();
            $this->verifyContainerIsCompleted($idContenedor);

            //just if current roles is almacen china
            if ($user->getNombreGrupo() == Usuario::ROL_ALMACEN_CHINA) {
                $this->sendAlertDifferenceCbmMessage($idCotizacion);
            }
            //Validate if provveedor has status R but not have qty box china and cbm total china OR ARE 0
            if ($proveedor->estados_proveedor == $this->STATUS_RECIVED && (!$proveedor->qty_box_china || $proveedor->qty_box_china == 0) && (!$proveedor->cbm_total_china || $proveedor->cbm_total_china == 0)) {
                //if have arrive date china, change status to C else if have datos_proveedor change to NC
                if ($proveedor->arrive_date_china) {
                    $proveedor->estados_proveedor = $this->STATUS_CONTACTED;
                    $proveedor->save();
                    try {
                        $carga = Contenedor::where('id', $idContenedor)->first()->carga;
                        $message = "China contacto al proveedor con codigo de proveedor " . $supplierCode . " del cliente " . $cotizacion->nombre . " del contenedor " . $carga . " y fecha de llegada " . $data['arrive_date_china'];
                        CotizacionChinaContacted::dispatch($cotizacion, $proveedor, $supplierCode, $data['arrive_date_china'], $message);
                    } catch (\Exception $e) {
                        Log::error('Error al disparar evento CotizacionChinaContacted: ' . $e->getMessage());
                    }
                    Log::info('proveedor status changed to C: ' . $proveedor->estados_proveedor);
                } else if ($proveedor->datos_proveedor) {
                    $proveedor->estados_proveedor = $this->STATUS_NOT_CONTACTED;
                    $proveedor->save();
                    Log::info('proveedor status changed to NC: ' . $proveedor->estados_proveedor);
                }
            }
            return response()->json([
                'success' => true,
                'message' => 'Datos actualizados correctamente',
                'data' => $proveedor
            ]);
        } catch (\Exception $e) {
            Log::error('Error en updateProveedorData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar datos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza únicamente el arrive_date de un proveedor.
     * Solo roles Cotizador y Coordinación pueden hacerlo.
     */
    public function updateArriveDate(Request $request, $idProveedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Usuario no autenticado'], 401);
            }

            $allowedRoles = [Usuario::ROL_COTIZADOR, Usuario::ROL_COORDINACION];
            if (!in_array($user->getNombreGrupo(), $allowedRoles)) {
                return response()->json(['success' => false, 'message' => 'No tienes permisos para realizar esta acción'], 403);
            }

            $proveedor = CotizacionProveedor::find($idProveedor);
            if (!$proveedor) {
                return response()->json(['success' => false, 'message' => 'Proveedor no encontrado'], 404);
            }

            $arriveDate = $request->input('arrive_date');
            if (empty($arriveDate)) {
                return response()->json(['success' => false, 'message' => 'arrive_date es requerido'], 422);
            }

            // Intentar parsear varias formas: d/m/Y, Y-m-d, Y-m-d H:i:s
            $dateTime = null;
            // dd/mm/YYYY
            $dateTime = \DateTime::createFromFormat('d/m/Y', $arriveDate);
            if (!$dateTime) {
                // YYYY-mm-dd or datetime
                $dateTime = \DateTime::createFromFormat('Y-m-d', $arriveDate) ?: \DateTime::createFromFormat('Y-m-d H:i:s', $arriveDate);
            }

            if (!$dateTime) {
                return response()->json(['success' => false, 'message' => 'Formato de fecha inválido. Usa dd/mm/YYYY o YYYY-mm-dd'], 422);
            }

            // Store only the date portion (YYYY-MM-DD) — drop time component
            $normalized = $dateTime->format('Y-m-d');
            $proveedor->arrive_date = $normalized;
            $proveedor->save();

            return response()->json(['success' => true, 'message' => 'arrive_date actualizado', 'data' => $proveedor]);
        } catch (\Exception $e) {
            Log::error('Error en updateArriveDate: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al actualizar arrive_date', 'error' => $e->getMessage()], 500);
        }
    }

    public function sendAlertDifferenceCbmMessage($idCotizacion)
    {
        try {
            Log::info('sendAlertDifferenceCbmMessage: ' . $idCotizacion);
            $cotizacion = Cotizacion::find($idCotizacion);
            $proveedores = CotizacionProveedor::where('id_cotizacion', $idCotizacion)->where('estados_proveedor', '!=', 'NO LOADED')->get();
            $telefono = $cotizacion->telefono;
            $telefono = preg_replace('/\s+/', '', $telefono);
            $this->phoneNumberId = $telefono ? $telefono . '@c.us' : '';
            //validate if all providers have cbm_total_china and cbm_total
            $sendMessage = false;
            foreach ($proveedores as $proveedor) {

                //if difference is greater than 0.50, send message
                if ($proveedor->cbm_total_china - $proveedor->cbm_total > 0.50) {
                    $sendMessage = true;
                    break;
                }
            }
            if ($sendMessage) {

                $totalDiferencia = 0;
                $message = "";
                foreach ($proveedores as $proveedor) {
                    $cbmTotalChina = $proveedor->cbm_total_china ?? 0;
                    $cbmTotal = $proveedor->cbm_total ?? 0;
                    $diferencia = $cbmTotalChina - $cbmTotal;
                    if ($diferencia > 0.50) {
                        $message .= "Proveedor codigo " . $proveedor->code_supplier . "\nCbm China: " . $cbmTotalChina . "\nCbm cliente: " . $cbmTotal . "\nDiferencia: " . $diferencia . "\n\n";
                        $totalDiferencia += $diferencia;
                    }
                }
                $message .= "Total diferencia: " . $totalDiferencia;
                $message .= "\n📦 Las variaciones en el cubicaje se cobrará la diferencia en la cotización final.";
                if ($cotizacion->send_alert_difference_cbm_status == 'PENDING') {
                    $response = $this->sendMessage($message);
                    if ($response && isset($response['status']) && $response['status'] === true) {
                        //update cotizacion set send_alert_difference_cbm_status = 'PENDING'
                        Log::info('sendAlertDifferenceCbmMessage sent: ' . $message);
                        $cotizacion->send_alert_difference_cbm_status = 'SENDED';
                        $cotizacion->save();
                    }
                } else {
                    Log::info('sendAlertDifferenceCbmMessage already sent: ' . $message);
                    return true;
                }
            }
        } catch (\Exception $e) {
            Log::error('Error en sendAlertDifferenceCbm: ' . $e->getMessage());
            return false;
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

            // Actualizar tracking siguiendo el patrón correcto
            $ahora = now();
            
            // Obtener el registro más reciente del tracking
            $trackingActual = DB::table($this->table_conteneodr_proveedor_estados_tracking)
                ->where('id_proveedor', $idProveedor)
                ->where('id_cotizacion', $idCotizacion)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($trackingActual) {
                // Actualizar el registro existente con updated_at
                DB::table($this->table_conteneodr_proveedor_estados_tracking)
                    ->where('id', $trackingActual->id)
                    ->update(['updated_at' => $ahora]);
            }

            // Insertar nuevo registro con el estado ROTULADO
            DB::table($this->table_conteneodr_proveedor_estados_tracking)
                ->insert([
                    'id_proveedor' => $idProveedor,
                    'id_cotizacion' => $idCotizacion,
                    'estado' => 'ROTULADO',
                    'created_at' => $ahora,
                    'updated_at' => $ahora
                ]);

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
            $files = $files->map(function ($file) {
                $file->file_url = $this->generateImageUrl($file->file_url);
                return $file;
            });
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
    public function deleteFileInspection($idFile)
    {
        try {
            $file = AlmacenInspection::find($idFile);
            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo no encontrado'
                ], 404);
            }
            if (Storage::exists($file->file_path)) {
                Storage::delete($file->file_path);
            }
            $file->delete();
            Log::info('Inspección archivo eliminado correctamente', ['idFile' => $idFile]);
            return response()->json([
                'success' => true,
                'message' => 'Archivo eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error en deleteFileInspection: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function deleteFileDocumentationPeru($idFile)
    {
        try {
            DB::beginTransaction();

            //delete file from contenedor_consolidado_cotizacion_documentacion
            $file = CotizacionDocumentacion::find($idFile);
            if (!$file) {
                return $this->jsonResponse(false, 'Archivo no encontrado', [], 404);
            }
            $file->delete();

            DB::commit();
            return $this->jsonResponse(true, 'Archivo eliminado correctamente', [], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->jsonResponse(false, 'Error al eliminar el archivo: ' . $e->getMessage(), [], 500);
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
            $files = $files->map(function ($file) {
                $file->file_url = $this->generateImageUrl($file->file_url);
                return $file;
            });
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
    public function validateToSendInspectionMessage($idProveedor, $idCotizacion)
    {
        try {
            // Validar autenticación
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return $this->jsonResponse(false, 'Usuario no autenticado', [], 401);
            }

            // Obtener datos necesarios
            $proveedor = $this->getProveedorData($idProveedor);
            if (!$proveedor) {
                return $this->jsonResponse(false, 'Proveedor no encontrado', [], 404);
            }

            $cotizacion = $this->getCotizacionData($proveedor->id_cotizacion);
            if (!$cotizacion) {
                return $this->jsonResponse(false, 'Cotización no encontrada', [], 404);
            }

            // Actualizar estado del proveedor
            $this->updateProveedorStatus($idProveedor);

            // Obtener archivos de inspección
            $inspectionFiles = $this->getInspectionFiles($idProveedor);

            // Preparar datos para mensajes
            $telefono = $this->formatPhoneNumber($cotizacion->telefono);
            $qtyBox = $proveedor->qty_box_china ?? $proveedor->qty_box;

            // Preparar mensaje inicial de inspección (se enviará solo una vez en sendInspectionFiles)
            $inspectionMessage = $this->buildInspectionMessage($cotizacion->nombre, $proveedor->code_supplier, $qtyBox);
            $proveedorsWithFilesSended = AlmacenInspection::where('id_cotizacion', $idCotizacion)
                ->where('send_status', 'SENDED')
                ->count();
            // Enviar archivos de inspección (el mensaje se envía una sola vez dentro de esta función)
            $sentFiles = $this->sendInspectionFiles($inspectionFiles, $inspectionMessage, $telefono, $proveedor->code_supplier);
            $usuarioActual = JWTAuth::parseToken()->authenticate();
            $cotizacion = Cotizacion::find($idCotizacion);
            $proveedor = CotizacionProveedor::find($idProveedor);
            $carga=Contenedor::where('id', $cotizacion->id_contenedor)->first();
            $this->dispararEventoYNotificacionProveedorInspeccionado($cotizacion, $proveedor, $proveedor->code_supplier, $carga, $usuarioActual);
            $this->crearNotificacionesProveedorInspeccionado($cotizacion, $proveedor, $proveedor->code_supplier, $carga, $usuarioActual);
    
           
            Log::info('proveedorsWithFilesSended: ' . $proveedorsWithFilesSended);
            if ($proveedorsWithFilesSended < 1) {
                if ($this->shouldSendReservationMessage($idCotizacion)) {
                    $this->sendReservationMessage($cotizacion, $telefono);
                }
                $pagosUrl = public_path('assets/images/pagos-full.jpg');

                $this->sendMedia($pagosUrl, 'image/jpg', null, $telefono, 15, 'consolidado', 'Numeros_de_cuenta.jpg');
            } else {
                return $this->jsonResponse(true, 'Proceso de inspección completado correctamente', [
                    'proveedors_with_files_sended' => $proveedorsWithFilesSended,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error en validateToSendInspectionMessage: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Error al procesar la inspección', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener datos del proveedor
     */
    private function getProveedorData($idProveedor)
    {
        return CotizacionProveedor::where('id', $idProveedor)
            ->select(['estados_proveedor', 'code_supplier', 'qty_box_china', 'qty_box', 'id_cotizacion'])
            ->first();
    }

    /**
     * Obtener datos de la cotización
     */
    private function getCotizacionData($idCotizacion)
    {
        return Cotizacion::where('id', $idCotizacion)
            ->select(['volumen', 'monto', 'id_contenedor', 'nombre', 'telefono'])
            ->first();
    }

    /**
     * Actualizar estado del proveedor a inspeccionado
     */
    private function updateProveedorStatus($idProveedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            //log updated by user id
            Log::info('updated by user id: ' . $user->id, ['idProveedor' => $idProveedor]);
            if (!$user) {
                Log::error('Usuario no autenticado', ['idProveedor' => $idProveedor]);
                return;
            }
            CotizacionProveedor::where('id', $idProveedor)
                ->update([
                    'estados_proveedor' => 'INSPECTION',
                    'estados' => 'INSPECCIONADO'
                ]);
            //if provider not has arrive_date_china, update with today's date
            $proveedor = CotizacionProveedor::where('id', $idProveedor)->first();
            if (!isset($proveedor->arrive_date_china) || $proveedor->arrive_date_china == null) {
                $proveedor->arrive_date_china = \Carbon\Carbon::now()->format('Y-m-d');
                $proveedor->save();
            }
        } catch (\Exception $e) {
            Log::error('Error en updateProveedorStatus: ' . $e->getMessage(), ['idProveedor' => $idProveedor]);
            return;
        }
    }

    /**
     * Obtener archivos de inspección del proveedor
     */
    private function getInspectionFiles($idProveedor)
    {
        return [
            'images' => AlmacenInspection::where('id_proveedor', $idProveedor)
                ->whereIn('file_type', ['image/jpeg', 'image/png', 'image/jpg'])
                ->where('send_status', 'PENDING')
                ->select(['id', 'file_path', 'file_type', 'send_status'])
                ->get(),
            'videos' => AlmacenInspection::where('id_proveedor', $idProveedor)
                ->where('file_type', 'video/mp4')
                ->where('send_status', 'PENDING')
                ->select(['id', 'file_path', 'file_type', 'send_status'])
                ->get()
        ];
    }

    /**
     * Construir mensaje de inspección
     */
    private function buildInspectionMessage($cliente, $codeSupplier, $qtyBox)
    {
        return $cliente . '----' . $codeSupplier . '----' . $qtyBox . ' boxes.' . "\n\n" .
            '📦 Tu carga llegó a nuestro almacén de Yiwu, te comparto las fotos y videos.' . "\n\n";
    }

    /**
     * Enviar archivos de inspección
     */
    private function sendInspectionFiles($inspectionFiles, $message, $telefono, $codeSupplier = null)
    {
        $sentFiles = ['images' => 0, 'videos' => 0];

        // Contar total de archivos a enviar
        $totalFiles = count($inspectionFiles['images']) + count($inspectionFiles['videos']);

        // Solo enviar mensaje si hay archivos para enviar
        if ($totalFiles > 0) {
            $this->sendMessage($message, $telefono);
        }

        // Enviar imágenes sin mensaje adicional
        foreach ($inspectionFiles['images'] as $image) {
            if ($this->sendSingleInspectionFile($image, $message, $telefono, $codeSupplier)) {
                $sentFiles['images']++;
            }
        }

        // Enviar videos sin mensaje adicional
        foreach ($inspectionFiles['videos'] as $video) {
            if ($this->sendSingleInspectionFile($video, $message, $telefono, $codeSupplier)) {
                $sentFiles['videos']++;
            }
        }

        return $sentFiles;
    }

    /**
     * Enviar un archivo individual de inspección usando URLs públicas
     * Similar a cómo lo hace SendInspectionMediaJob
     */
    private function sendSingleInspectionFile($file, $message, $telefono, $codeSupplier = null)
    {
        // Validar que el archivo tenga una ruta
        if (empty($file->file_path)) {
            Log::error('Archivo de inspección no tiene ruta: ' . $file->id);
            return false;
        }

        // Generar nombre del archivo con el código del proveedor (como en SendInspectionMediaJob)
        $extension = pathinfo($file->file_path, PATHINFO_EXTENSION);
        $fileName = $codeSupplier ? $codeSupplier . '.' . $extension : basename($file->file_path);

        Log::info('Enviando archivo de inspección con URL pública', [
            'file_id' => $file->id,
            'file_path' => $file->file_path,
            'code_supplier' => $codeSupplier,
            'file_name' => $fileName
        ]);

        // Mensaje con código del proveedor (como en SendInspectionMediaJob)
        $messageToSend = $codeSupplier ?? '';

        // Usar sendMediaInspectionToController para enviar con URL pública
        // Este método internamente genera la URL pública desde la ruta relativa
        $response = $this->sendMediaInspectionToController(
            $file->file_path,  // Ruta relativa almacenada en BD (ej: 'inspection/archivo.jpg')
            $file->file_type,
            $messageToSend,
            $telefono,
            0,  // Sin sleep (como en SendInspectionMediaJob)
            $file->id,
            $fileName
        );

        // Verificar que la respuesta sea exitosa antes de actualizar el estado
        if ($response && isset($response['status']) && $response['status'] === true) {
            $file->update(['send_status' => 'SENDED']);
            Log::info('Archivo de inspección enviado exitosamente con URL pública', [
                'file_id' => $file->id,
                'file_path' => $file->file_path,
                'code_supplier' => $codeSupplier
            ]);
            return true;
        } else {
            Log::warning('Error al enviar archivo de inspección', [
                'file_id' => $file->id,
                'file_path' => $file->file_path,
                'code_supplier' => $codeSupplier,
                'response' => $response
            ]);
        }

        return false;
    }


    private function shouldSendReservationMessage($idCotizacion)
    {
        $totalProviders = CotizacionProveedor::where('id_cotizacion', $idCotizacion)->count();
        $inspectedProviders = CotizacionProveedor::where('id_cotizacion', $idCotizacion)
            ->where('estados_proveedor', 'INSPECTION')
            ->count();

        // Solo enviar si es el primer proveedor inspeccionado y hay más de 1 proveedor en total
        return $inspectedProviders >= 1 && $totalProviders >= 1;
    }

    private function sendReservationMessage($cotizacion, $telefono)
    {
        $contenedor = Contenedor::where('id', $cotizacion->id_contenedor)->first();
        if (!$contenedor) {
            return;
        }

        $fechaCierre = $this->formatFechaCierre($contenedor->f_cierre);

        $message = "Reserva de espacio:\n" .
            "*Consolidado #{$contenedor->carga}-2025*\n\n" .
            "Ahora tienes que hacer el pago del CBM preliminar para poder subir su carga en nuestro contenedor.\n\n" .
            "☑ CBM Preliminar: {$cotizacion->volumen} cbm\n" .
            "☑ Costo CBM: \${$cotizacion->monto}\n" .
            "☑ Fecha Limite de pago: {$fechaCierre}\n\n" .
            "⚠ Nota: Realizar el pago antes del llenado del contenedor.\n\n" .
            "📦 En caso hubiera variaciones en el cubicaje se cobrará la diferencia en la cotización final.\n\n" .
            "Apenas haga el pago, envíe por este medio para hacer la reserva.";

        $this->sendMessage($message, $telefono, 10);
    }

    /**
     * Formatear fecha de cierre
     */
    private function formatFechaCierre($fecha)
    {
        if (!$fecha) {
            return 'Fecha no especificada';
        }

        $fechaFormateada = Carbon::parse($fecha)->format('d F');
        $meses = [
            'January' => 'Enero',
            'February' => 'Febrero',
            'March' => 'Marzo',
            'April' => 'Abril',
            'May' => 'Mayo',
            'June' => 'Junio',
            'July' => 'Julio',
            'August' => 'Agosto',
            'September' => 'Septiembre',
            'October' => 'Octubre',
            'November' => 'Noviembre',
            'December' => 'Diciembre'
        ];

        return str_replace(array_keys($meses), array_values($meses), $fechaFormateada);
    }

    /**
     * Método auxiliar para respuestas JSON
     */
    private function jsonResponse($success, $message, $data = [], $status = 200)
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    /**
     * Validar y enviar mensaje de inspección (versión 2)
     */

    /**
     * Método de prueba para enviar medios de inspección
     */
    public function testSendMediaInspection(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $idProveedor = $request->idProveedor;
            if (!$idProveedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de proveedor requerido'
                ], 400);
            }

            // Obtener archivos de inspección del proveedor
            $inspecciones = AlmacenInspection::where('id_proveedor', $idProveedor)
                ->whereIn('file_type', ['image/jpeg', 'image/png', 'image/jpg', 'video/mp4'])
                ->select(['id', 'file_path', 'file_type', 'send_status'])
                ->get();

            if ($inspecciones->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron archivos de inspección para este proveedor'
                ], 404);
            }

            $resultados = [];
            $telefono = '51912705923@c.us'; // Número de prueba

            foreach ($inspecciones as $inspeccion) {
                $fileSystemPath = storage_path('app/public/' . $inspeccion->file_path);

                Log::info('Probando envío de archivo', [
                    'id' => $inspeccion->id,
                    'file_path' => $inspeccion->file_path,
                    'fileSystemPath' => $fileSystemPath,
                    'exists' => file_exists($fileSystemPath),
                    'readable' => is_readable($fileSystemPath),
                    'size' => file_exists($fileSystemPath) ? filesize($fileSystemPath) : 'N/A'
                ]);

                if (file_exists($fileSystemPath) && is_readable($fileSystemPath)) {
                    $resultado = $this->sendMediaInspection(
                        $fileSystemPath,
                        $inspeccion->file_type,
                        'Mensaje de prueba',
                        $telefono,
                        0,
                        $inspeccion->id
                    );

                    $resultados[] = [
                        'id' => $inspeccion->id,
                        'file_name' => basename($inspeccion->file_path),
                        'file_type' => $inspeccion->file_type,
                        'file_size' => filesize($fileSystemPath),
                        'resultado' => $resultado
                    ];
                } else {
                    $resultados[] = [
                        'id' => $inspeccion->id,
                        'file_name' => basename($inspeccion->file_path),
                        'file_type' => $inspeccion->file_type,
                        'error' => 'Archivo no encontrado o no legible',
                        'fileSystemPath' => $fileSystemPath
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Prueba de envío completada',
                'data' => [
                    'total_archivos' => $inspecciones->count(),
                    'resultados' => $resultados
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en testSendMediaInspection: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al probar envío de medios',
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
    private function generateImageUrl($ruta)
    {
        if (empty($ruta)) {
            return null;
        }

        // Si ya es una URL completa, verificar si tiene doble storage y corregirlo
        if (filter_var($ruta, FILTER_VALIDATE_URL)) {
            // Corregir URLs con doble storage
            if (strpos($ruta, '/storage//storage/') !== false) {
                $ruta = str_replace('/storage//storage/', '/storage/', $ruta);
            }
            return $ruta;
        }

        // Limpiar la ruta de barras iniciales para evitar doble slash
        $ruta = ltrim($ruta, '/');

        // Corregir rutas con doble storage
        if (strpos($ruta, 'storage//storage/') !== false) {
            $ruta = str_replace('storage//storage/', 'storage/', $ruta);
        }

        // Si la ruta ya contiene 'storage/', no agregar otro 'storage/'
        if (strpos($ruta, 'storage/') === 0) {
            $baseUrl = config('app.url');
            return rtrim($baseUrl, '/') . '/' . $ruta;
        }

        // Si la ruta empieza con 'contratos/', usar ruta con CORS para desarrollo
        if (strpos($ruta, 'contratos/') === 0) {
            $baseUrl = config('app.url');
            // En desarrollo, usar /files/ para que pase por el FileController con CORS
            if (config('app.env') === 'local') {
                return rtrim($baseUrl, '/') . '/files/' . $ruta;
            }
            // En producción, usar /storage/ directamente
            return rtrim($baseUrl, '/') . '/storage/' . $ruta;
        }

        // Si la ruta empieza con 'public/', remover 'public/' y agregar 'storage/'
        if (strpos($ruta, 'public/') === 0) {
            $ruta = substr($ruta, 7); // Remover 'public/'
            $baseUrl = config('app.url');
            return rtrim($baseUrl, '/') . '/storage/' . $ruta;
        }

        // Construir URL manualmente para evitar problemas con Storage::url()
        $baseUrl = config('app.url');
        $storagePath = 'storage/';

        // Asegurar que no haya doble slash
        $baseUrl = rtrim($baseUrl, '/');
        $storagePath = ltrim($storagePath, '/');
        $ruta = ltrim($ruta, '/');

        return $baseUrl . '/' . $storagePath . $ruta;
    }

    /**
     * Agregar o actualizar nota del proveedor
     */
    public function addNote(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $idProveedor = $request->id_proveedor;
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $request->validate([
                'notas' => 'required|string|max:1000'
            ]);

            $proveedor = CotizacionProveedor::find($idProveedor);
            if (!$proveedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado'
                ], 404);
            }

            $proveedor->nota = $request->notas;
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
    public function saveInspection(Request $request)
    {
        try {
            $idProveedor = $request->idProveedor;
            $idCotizacion = $request->idCotizacion;

            // Verificar que se hayan enviado archivos
            if (!$request->hasFile('files')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se han enviado archivos'
                ], 400);
            }

            $files = $request->file('files');

            // Si es un solo archivo, convertirlo en array
            if (!is_array($files)) {
                $files = [$files];
            }


            $archivosGuardados = [];

            foreach ($files as $file) {
                if ($file->isValid()) {
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs(self::INSPECTION_PATH, $filename, 'public');
                    $inspeccion = new AlmacenInspection();
                    $inspeccion->file_name = $file->getClientOriginalName();
                    $inspeccion->file_type = $file->getMimeType();
                    $inspeccion->file_size = $file->getSize();
                    $inspeccion->last_modified = now();
                    $inspeccion->file_ext = $file->getClientOriginalExtension();
                    $inspeccion->send_status = 'PENDING';
                    $inspeccion->id_proveedor = $idProveedor;
                    $inspeccion->file_path = $path;
                    $inspeccion->id_cotizacion = $idCotizacion;
                    $inspeccion->save();

                    $archivosGuardados[] = [
                        'id' => $inspeccion->id,
                        'nombre' => $inspeccion->file_name,
                        'ruta' => $path,
                        'tamaño' => $inspeccion->file_size
                    ];

                    Log::info('Archivo de inspección guardado:', [
                        'nombre_original' => $file->getClientOriginalName(),
                        'ruta_storage' => $path,
                        'tamaño' => $file->getSize()
                    ]);
                } else {
                    Log::warning('Archivo de inspección inválido:', ['nombre' => $file->getClientOriginalName()]);
                }
            }

            if (empty($archivosGuardados)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo guardar ningún archivo de inspección'
                ], 400);
            }
            Log::info('Inspección guardada correctamente', ['idProveedor' => $idProveedor, 'idCotizacion' => $idCotizacion]);
            $this->validateToSendInspectionMessage($idProveedor, $idCotizacion);
            return response()->json([
                'success' => true,
                'message' => 'Inspección guardada correctamente',
                'data' => [
                    'archivos_guardados' => $archivosGuardados,
                    'total_archivos' => count($archivosGuardados)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al guardar inspección: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar inspección',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function saveDocumentation(Request $request)
    {
        try {
            $idProveedor = $request->idProveedor;
            $idCotizacion = $request->idCotizacion;

            // Verificar que se hayan enviado archivos
            if (!$request->hasFile('files')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se han enviado archivos'
                ], 400);
            }
            $files = $request->file('files');

            // Si es un solo archivo, convertirlo en array
            if (!is_array($files)) {
                $files = [$files];
            }

            Log::info('Archivos recibidos:', ['cantidad' => count($files)]);

            $archivosGuardados = [];

            foreach ($files as $file) {
                if ($file->isValid()) {
                    // Generar nombre único para el archivo
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

                    // Guardar archivo en storage
                    $path = $file->storeAs(self::DOCUMENTATION_PATH, $filename, 'public');

                    // Crear registro en la base de datos
                    $documentacion = new AlmacenDocumentacion();
                    $documentacion->file_name = $file->getClientOriginalName();
                    $documentacion->file_type = $file->getMimeType();
                    $documentacion->file_size = $file->getSize();
                    $documentacion->last_modified = now();
                    $documentacion->file_ext = $file->getClientOriginalExtension();
                    $documentacion->file_path = $path;
                    $documentacion->id_proveedor = $idProveedor;
                    $documentacion->id_cotizacion = $idCotizacion;
                    $documentacion->save();

                    $archivosGuardados[] = [
                        'id' => $documentacion->id,
                        'nombre' => $documentacion->file_name,
                        'ruta' => $path,
                        'tamaño' => $documentacion->file_size
                    ];

                    Log::info('Archivo guardado:', [
                        'nombre_original' => $file->getClientOriginalName(),
                        'ruta_storage' => $path,
                        'tamaño' => $file->getSize()
                    ]);
                } else {
                    Log::warning('Archivo inválido:', ['nombre' => $file->getClientOriginalName()]);
                }
            }

            if (empty($archivosGuardados)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo guardar ningún archivo'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Documentación guardada correctamente',
                'data' => [
                    'archivos_guardados' => $archivosGuardados,
                    'total_archivos' => count($archivosGuardados)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al guardar documentación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar documentación',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getCotizacionProveedorByIdCotizacion($idCotizacion)
    {
        $proveedores = CotizacionProveedor::with('items')->where('id_cotizacion', $idCotizacion)->get();
        if (!$proveedores) {
            return response()->json([
                'success' => false,
                'message' => 'Proveedores no encontrados'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $proveedores
        ]);
    }
    public function getCotizacionProveedor($idProveedor)
    {
        try {
            // Eager load contenedor and only the needed fields from cotizacion
            $proveedor = CotizacionProveedor::with([
                'contenedor',
                'cotizacion:id,nombre'
            ])
                ->where('id', $idProveedor)
                ->first();

            if (!$proveedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado'
                ], 404);
            }

            // Append client name (from cotizacion.nombre) for convenience
            $proveedor->cliente_nombre = optional($proveedor->cotizacion)->nombre;

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
    public function verifyContainerIsCompleted($idcontenedor)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $cotizacion = Cotizacion::where('id_contenedor', $idcontenedor)->first();
        $listaEmbarque = $cotizacion->lista_embarque_url;
        $blFile = $cotizacion->bl_file_url;

        // Buscar si existe proveedor con estado DATOS PROVEEDOR
        $estadoProveedores = CotizacionProveedor::where('id_cotizacion', $idcontenedor)
            ->where('estados', 'DATOS PROVEEDOR')
            ->get();

        $estado = null;
        foreach ($estadoProveedores as $estadoProveedor) {
            if ($estadoProveedor->estado == "DATOS PROVEEDOR") {
                $estado = "DATOS PROVEEDOR";
                break;
            }
        }

        // Preparar los datos para actualizar
        $updateData = [];

        if ($listaEmbarque != null) {
            $updateData['estado_china'] = 'COMPLETADO';
        } else if ($estado == "DATOS PROVEEDOR") {
            // No hacer nada
        } else {
            if ($user->No_Grupo == 'Coordinación') {
                $updateData['estado'] = 'RECIBIENDO';
            }
        }

        // Solo actualizar si hay datos para actualizar
        if (!empty($updateData)) {
            DB::table('carga_consolidada_contenedor')
                ->where('id', $idcontenedor)
                ->update($updateData);
        }
    }
    public function refreshRotuladoStatus($id)
    {
        try {
            $proveedor = CotizacionProveedor::find($id);
            $proveedor->send_rotulado_status = 'PENDING';
            $proveedor->save();
            return response()->json(['success' => true, 'message' => 'Estado actualizado correctamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al actualizar estado', 'error' => $e->getMessage()], 500);
        }
    }
    public function forceSendInspection(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $idContainer = $request->idContainer;
            $idCotizacion = $request->idCotizacion;
            $idsProveedores = $request->proveedores;

            if (empty($idsProveedores) || !is_array($idsProveedores)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe proporcionar al menos un proveedor'
                ], 400);
            }

            Log::info("Iniciando proceso de envío de inspección", [
                'id_cotizacion' => $idCotizacion,
                'id_container' => $idContainer,
                'proveedores' => $idsProveedores,
                'user_id' => $user->ID_Usuario
            ]);

            // Despachar jobs para cada proveedor
            foreach ($idsProveedores as $idProveedor) {
                SendInspectionMediaJob::dispatch(
                    $idProveedor,
                    $idCotizacion,
                    $idsProveedores,
                    $user->ID_Usuario
                )->onQueue('importaciones');

                Log::info("Job de inspección despachado", [
                    'id_proveedor' => $idProveedor,
                    'id_cotizacion' => $idCotizacion
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Proceso de inspección iniciado correctamente',
                'data' => [
                    'proveedores_procesados' => count($idsProveedores),
                    'jobs_despachados' => count($idsProveedores),
                    'nota' => 'Los archivos se están procesando en segundo plano'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en forceSendInspection: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar el proceso de inspección',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resuelve la ruta de un archivo, manejando tanto rutas locales como URLs externas
     * 
     * @param string $filePath Ruta del archivo (puede ser local o URL)
     * @return string|false Ruta del archivo accesible o false si falla
     */
    private function resolveMediaPath($filePath)
    {
        try {
            Log::info("Resolviendo ruta de archivo: " . $filePath);

            // Verificar si es una URL externa
            if (filter_var($filePath, FILTER_VALIDATE_URL)) {
                Log::info("URL externa detectada, descargando: " . $filePath);
                return $this->downloadExternalMedia($filePath);
            }

            // Si no es URL, intentar como ruta local
            $possiblePaths = [
                // Ruta directa si ya es absoluta
                $filePath,
                // Ruta en storage/app/public
                storage_path('app/public/' . $filePath),
                // Ruta en public
                public_path($filePath),
                // Ruta relativa desde storage
                storage_path($filePath),
                // Limpiar posibles barras dobles y probar
                storage_path('app/public/' . ltrim($filePath, '/')),
                public_path(ltrim($filePath, '/'))
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    Log::info("Archivo encontrado en: " . $path);
                    return $path;
                }
            }

            Log::error("Archivo no encontrado en ninguna ruta", [
                'file_path' => $filePath,
                'attempted_paths' => $possiblePaths
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error("Error al resolver ruta de archivo: " . $e->getMessage(), [
                'file_path' => $filePath,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Descarga un archivo desde una URL externa y lo guarda temporalmente
     * 
     * @param string $url URL del archivo a descargar
     * @return string|false Ruta del archivo temporal o false si falla
     */
    private function downloadExternalMedia($url)
    {
        try {
            Log::info("Descargando archivo externo: " . $url);

            // Verificar si cURL está disponible
            if (!function_exists('curl_init')) {
                Log::error("cURL no está disponible en el servidor");
                return false;
            }

            // Inicializar cURL
            $ch = curl_init();

            if (!$ch) {
                Log::error("No se pudo inicializar cURL");
                return false;
            }

            // Configurar opciones de cURL
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                CURLOPT_HTTPHEADER => [
                    'Accept: image/*,video/*,*/*',
                    'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                ],
            ]);

            // Ejecutar la petición
            $fileContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

            curl_close($ch);

            // Verificar errores
            if ($fileContent === false || !empty($error)) {
                Log::error("Error cURL al descargar archivo: " . $error, ['url' => $url]);
                return false;
            }

            if ($httpCode !== 200) {
                Log::error("Error HTTP al descargar archivo. Código: " . $httpCode, [
                    'url' => $url,
                    'content_type' => $contentType
                ]);
                return false;
            }

            if (empty($fileContent)) {
                Log::error("Archivo descargado está vacío", ['url' => $url]);
                return false;
            }

            // Determinar extensión del archivo
            $extension = $this->getFileExtensionFromUrl($url, $contentType);

            // Crear archivo temporal
            $tempFile = tempnam(sys_get_temp_dir(), 'media_') . '.' . $extension;

            if (file_put_contents($tempFile, $fileContent) === false) {
                Log::error("No se pudo crear el archivo temporal");
                return false;
            }

            Log::info("Archivo descargado exitosamente", [
                'url' => $url,
                'temp_file' => $tempFile,
                'size' => strlen($fileContent),
                'content_type' => $contentType
            ]);

            return $tempFile;
        } catch (\Exception $e) {
            Log::error("Excepción al descargar archivo externo: " . $e->getMessage(), [
                'url' => $url,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Obtiene la extensión de archivo basada en la URL y content-type
     * 
     * @param string $url URL del archivo
     * @param string $contentType Content-Type del archivo
     * @return string Extensión del archivo
     */
    private function getFileExtensionFromUrl($url, $contentType = null)
    {
        // Intentar obtener extensión de la URL
        $pathInfo = pathinfo(parse_url($url, PHP_URL_PATH));
        if (!empty($pathInfo['extension'])) {
            return strtolower($pathInfo['extension']);
        }

        // Si no hay extensión en la URL, usar content-type
        if ($contentType) {
            $mimeToExtension = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'video/mp4' => 'mp4',
                'video/avi' => 'avi',
                'video/mov' => 'mov',
                'video/wmv' => 'wmv',
                'application/pdf' => 'pdf'
            ];

            $mainType = strtok($contentType, ';'); // Remover parámetros como charset
            if (isset($mimeToExtension[$mainType])) {
                return $mimeToExtension[$mainType];
            }
        }

        // Por defecto, usar extensión genérica
        return 'tmp';
    }
    public function forceSendRotulado(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $idCotizacion = $request->idCotizacion;
            $idContainer = $request->idContainer;
            $idsProveedores = $request->proveedores;

            Log::info("Iniciando proceso de envío de rotulado", [
                'id_cotizacion' => $idCotizacion,
                'id_container' => $idContainer,
                'proveedores' => $idsProveedores,
                'user_id' => $user->ID_Usuario
            ]);

            // Despachar el job para procesar en segundo plano
            ForceSendRotuladoJob::dispatch($idCotizacion, $idsProveedores, $idContainer)->onQueue('importaciones');

            Log::info("Job ForceSendRotuladoJob despachado", [
                'id_cotizacion' => $idCotizacion,
                'id_container' => $idContainer,
                'proveedores' => $idsProveedores,
                'user_id' => $user->ID_Usuario
            ]);

            return response()->json([
                'success' => true,
                'message' => "Proceso de rotulado iniciado. Los mensajes se enviarán en segundo plano.",
                'data' => [
                    'id_cotizacion' => $idCotizacion,
                    'id_container' => $idContainer,
                    'proveedores' => $idsProveedores
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en forceSendRotulado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar envío de rotulado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Forzar envío de mensaje de cobranza a múltiples proveedores
     */
    public function forceSendCobrando(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $idCotizacion = $request->idCotizacion;
            $idContainer = $request->idContainer;


            Log::info("Iniciando proceso de envío de cobranza", [
                'id_cotizacion' => $idCotizacion,
                'id_container' => $idContainer,
                'user_id' => $user->ID_Usuario
            ]);

            // Despachar el job para procesar en segundo plano
            ForceSendCobrandoJob::dispatch($idCotizacion, $idContainer)->onQueue('importaciones');

            Log::info("Job ForceSendCobrandoJob despachado", [
                'id_cotizacion' => $idCotizacion,
                'id_container' => $idContainer,
                'user_id' => $user->ID_Usuario
            ]);

            return response()->json([
                'success' => true,
                'message' => "Proceso de cobranza iniciado. El mensaje se enviará en segundo plano.",
                'data' => [
                    'id_cotizacion' => $idCotizacion,
                    'id_container' => $idContainer
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en forceSendCobrando: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar envío de cobranza',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function forceSendMove(Request $request)
    {
        DB::beginTransaction();

        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'

                ], 401);
            }

            $idContainer = $request->idContainer;
            $idContainerDestino = $request->idContainerDestino;
            $idContainerPagoDestino = $request->idContainerPagoDestino;
            $idCotizacion = $request->idCotizacion;
            $proveedores = $request->proveedores;
            //busca la cotizacion con ese id , clona su datos pero con el id_contenedor de idContainerDestino y luego mueve los proveedores con los id des proveedores([1,2,3]) a la nueva cotizacion cambiando el id_cotizacion  a la nueva cotizacion y tambien cambia el id_contenedor de los proveedores a idContainerDestino y id_contenedor_pago  a idContainerPagoDestino y luego actualiza los datos de la nueva cotizacion y los proveedores
            $cotizacion = Cotizacion::find($idCotizacion);

            $cotizacionDestino = $cotizacion->replicate();
            $cotizacion->id_contenedor_pago = $idContainerDestino;
            $cotizacion->save();
            //generate new uuid
            $uuid = Str::uuid()->toString();
            $cotizacionDestino->uuid = $uuid;
            $cotizacionDestino->id_contenedor = $idContainerDestino;
            $cotizacionDestino->save();
            foreach ($proveedores as $proveedor) {
                $proveedor = CotizacionProveedor::find($proveedor);
                $proveedor->id_cotizacion = $cotizacionDestino->id;
                $proveedor->id_contenedor = $idContainerDestino;
                $proveedor->id_contenedor_pago = $idContainerPagoDestino;
                $proveedor->save();
            }
            Log::info("Iniciando proceso de envío de movimiento", [
                'id_container' => $idContainer,
                'id_container_destino' => $idContainerDestino,
                'id_container_pago_destino' => $idContainerPagoDestino,
                'id_cotizacion' => $idCotizacion,
                'proveedores' => $proveedores,
                'user_id' => $user->ID_Usuario
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Cotización movida correctamente',
                'data' => [
                    'id_cotizacion' => $idCotizacion,
                    'id_container' => $idContainer,
                    'id_container_destino' => $idContainerDestino,
                    'id_container_pago_destino' => $idContainerPagoDestino,
                    'proveedores' => $proveedores
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en forceSendMove: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar envío de movimiento',
                'error' => $e->getMessage()
            ], 500);
        } finally {
        }
    }
    public function forceSendRecordatorioDatosProveedor(Request $request)
    {
        $idCotizacion = $request->idCotizacion;
        $idContainer = $request->idContainer;
        $proveedores = $request->proveedores;

        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Despachar el Job para procesar el envío de manera asíncrona
            SendRecordatorioDatosProveedorJob::dispatch($idCotizacion, $idContainer, $proveedores)->onQueue('importaciones');

            return response()->json([
                'success' => true,
                'message' => 'Recordatorio de datos de proveedor programado para envío',
                'data' => [
                    'id_cotizacion' => $idCotizacion,
                    'id_container' => $idContainer,
                    'proveedores' => $proveedores
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en forceSendRecordatorioDatosProveedor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar envío de recordatorio de datos de proveedor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formatear número de teléfono para WhatsApp
     *
     * @param string $telefono
     * @return string
     */
    private function formatPhoneNumber($telefono)
    {
        // Remover espacios y caracteres especiales
        $telefono = preg_replace('/[^0-9]/', '', $telefono);

        // Si no tiene código de país, agregar +51 (Perú)
        if (strlen($telefono) === 9) {
            $telefono = '51' . $telefono;
        } elseif (strlen($telefono) === 10 && substr($telefono, 0, 1) === '0') {
            $telefono = '51' . substr($telefono, 1);
        }

        return $telefono . '@c.us';
    }
    public function downloadEmbarque($idContenedor, Request $request)
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

            $rol = $user->getNombreGrupo();

            $estadoChina = $request->estado_china ?? 'todos';
            $search = $request->search ?? '';

            // Usar la misma lógica de filtros que getContenedorCotizacionProveedores
            $query = DB::table('contenedor_consolidado_cotizacion AS main')
                ->select([
                    'main.*',
                    'U.No_Nombres_Apellidos'
                ])
                ->leftJoin('contenedor_consolidado_tipo_cliente AS TC', 'TC.id', '=', 'main.id_tipo_cliente')
                ->leftJoin('usuario AS U', 'U.ID_Usuario', '=', 'main.id_usuario')
                ->where('main.id_contenedor', $idContenedor);

            if (!empty($search)) {
                Log::info('search: ' . $search);
                $query->where('main.nombre', 'LIKE', '%' . $search . '%');
            }

            if ($request->has('estado_coordinacion') || $request->has('estado_china')) {
                $query->whereExists(function ($sub) use ($request) {
                    $sub->select(DB::raw(1))
                        ->from('contenedor_consolidado_cotizacion_proveedores as proveedores')
                        ->whereRaw('proveedores.id_cotizacion = main.id');

                    $sub->where(function ($q) use ($request) {
                        if ($request->has('estado_coordinacion') && $request->estado_coordinacion != 'todos') {
                            $q->where('proveedores.estados', $request->estado_coordinacion);
                        }
                        if ($request->has('estado_china') && $request->estado_china != 'todos') {
                            $q->orWhere('proveedores.estados_proveedor', $request->estado_china);
                        }
                    });
                });
            }

            // Aplicar filtros de rol
            switch ($rol) {
                case Usuario::ROL_COTIZADOR:
                    if ($user->getIdUsuario() != 28791 && $user->getIdUsuario() != 28911) {
                        $query->where('main.id_usuario', $user->getIdUsuario());
                    }
                    break;

                case Usuario::ROL_DOCUMENTACION:
                    $query->where('main.estado_cotizador', 'CONFIRMADO');
                    break;

                case Usuario::ROL_COORDINACION:
                    $query->where('main.estado_cotizador', 'CONFIRMADO');
                    break;

                case Usuario::ROL_ALMACEN_CHINA:
                    $query->where('main.estado_cotizador', 'CONFIRMADO');
                    break;
            }

            // Aplicar filtro whereNull después de los filtros de rol
            $query->whereNull('main.id_cliente_importacion');
            $query->orderBy('main.id', 'asc');

            // Obtener todas las cotizaciones sin paginación para el export
            $cotizaciones = $query->get();

            // Procesar datos igual que en getContenedorCotizacionProveedores
            $dataProcessed = collect($cotizaciones)->map(function ($item) use ($user, $estadoChina, $rol, $search) {
                // Obtener proveedores por separado
                $proveedoresQuery = DB::table('contenedor_consolidado_cotizacion_proveedores')
                    ->where('id_cotizacion', $item->id)
                    ->select([
                        'id',
                        'qty_box',
                        'peso',
                        'id_cotizacion',
                        'cbm_total',
                        'supplier',
                        'code_supplier',
                        'estados_proveedor',
                        'estados',
                        'supplier_phone',
                        'cbm_total_china',
                        'qty_box_china',
                        'products',
                        'estado_china',
                        'arrive_date_china',
                        'send_rotulado_status'
                    ])
                    ->get()
                    ->toArray();

                // Convertir a array asociativo y agregar id_proveedor
                $proveedores = array_map(function ($proveedor) {
                    $proveedorArray = (array)$proveedor;
                    $proveedorArray['id_proveedor'] = $proveedorArray['id'];
                    return $proveedorArray;
                }, $proveedoresQuery);

                // Forzar que siempre sea un array indexado numéricamente
                $proveedores = array_values($proveedores);

                // Filtrar proveedores por estado_china si es necesario
                if ($rol == Usuario::ROL_ALMACEN_CHINA && $estadoChina != "todos") {
                    $proveedores = array_filter($proveedores, function ($proveedor) use ($estadoChina) {
                        return ($proveedor['estados_proveedor'] ?? '') === $estadoChina;
                    });
                    // Reindexar después del filtro para mantener índices secuenciales
                    $proveedores = array_values($proveedores);
                }

                return [
                    'id' => $item->id,
                    'id_contenedor' => $item->id_contenedor,
                    'id_usuario' => $item->id_usuario,
                    'id_contenedor_pago' => $item->id_contenedor_pago,
                    'id_tipo_cliente' => $item->id_tipo_cliente,
                    'nombre' => $item->nombre,
                    'telefono' => $item->telefono,
                    'estado_cotizador' => $item->estado_cotizador,
                    'fecha_confirmacion' => $item->fecha_confirmacion,
                    'No_Nombres_Apellidos' => $item->No_Nombres_Apellidos,
                    'proveedores' => $proveedores,
                ];
            })->filter()->values();

            // Generar el Excel usando la clase Export
            return Excel::download(new EmbarqueExport($dataProcessed->toArray()), 'embarque_' . $idContenedor . '_' . date('Y-m-d_H-i-s') . '.xlsx');
        } catch (\Exception $e) {
            Log::error('Error en downloadEmbarque: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el archivo de embarque',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function sendRotulado(Request $request)
    {

        $idCotizacion = $request->idCotizacion;
        $proveedores = $request->proveedores;
        $cotizacion = Cotizacion::find($idCotizacion);
        $total_movilidad_personal = $request->total_movilidad_personal ?? 0;
        if (!$cotizacion) {
            return response()->json([
                'success' => false,
                'message' => 'Cotización no encontrada'
            ], 404);
        }

        if (!$proveedores) {
            return response()->json([
                'success' => false,
                'message' => 'Proveedores no encontrados'
            ], 404);
        }
        $this->procesarEstadoRotuladoJob($cotizacion->nombre, $cotizacion->carga, $proveedores, $idCotizacion, $total_movilidad_personal);
        return response()->json([
            'success' => true,
            'message' => 'Rotulado enviado correctamente'
        ]);
    }
    public function getUnsignedServiceContract($uuid)
    {
        $cotizacion = Cotizacion::where('uuid', $uuid)->first();
        if (!$cotizacion) {
            return response()->json([
                'success' => false,
                'message' => 'Cotización no encontrada'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Contrato de servicio no firmado',
            'data' => [
                'cotizacion_contrato_url' => $this->generateImageUrl($cotizacion->cotizacion_contrato_url),
                'cotizacion_contrato_firmado_url' => $this->generateImageUrl($cotizacion->cotizacion_contrato_firmado_url),
                'uuid' => $cotizacion->uuid,
                'cod_contrato' => $cotizacion->cod_contract
            ]
        ]);
    }
    public function signServiceContract($uuid, Request $request)
    {
        try {
            $cotizacion = Cotizacion::where('uuid', $uuid)->first();
            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ], 404);
            }

            // Validar que se haya enviado el archivo firmado
            if (!$request->hasFile('signed_file')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo firmado requerido'
                ], 400);
            }

            $signedFile = $request->file('signed_file');

            // Validar que sea un archivo válido
            if (!$signedFile->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo inválido'
                ], 400);
            }
            // Crear directorio si no existe
            $contratosDir = public_path('storage/contratos');
            if (!file_exists($contratosDir)) {
                mkdir($contratosDir, 0755, true);
            }

            // Verificar si es una imagen para generar contrato con firma
            $mimeType = $signedFile->getMimeType();
            $isImage = in_array($mimeType, ['image/png', 'image/jpeg', 'image/jpg', 'image/gif']);

            if ($isImage) {
                // Generar contrato completo con firma
                $pdfFilename = $uuid . '_signed_contract.pdf';
                $pdfPath = $contratosDir . '/' . $pdfFilename;

                // Eliminar archivo anterior si existe
                if (file_exists($pdfPath)) {
                    unlink($pdfPath);
                }

                // Obtener información del contenedor para el contrato
                $contenedor = \App\Models\CargaConsolidada\Contenedor::find($cotizacion->id_contenedor);
                $carga = $contenedor ? $contenedor->carga : 'N/A';

                // Convertir firma a base64
                $imagePath = $signedFile->getPathname();
                $imageData = base64_encode(file_get_contents($imagePath));
                $signatureBase64 = 'data:' . $mimeType . ';base64,' . $imageData;

                // Datos para la vista del contrato firmado
                $viewData = [
                    'fecha' => date('d-m-Y'),
                    'cliente_nombre' => $cotizacion->nombre,
                    'cliente_documento' => $cotizacion->documento,
                    'cliente_domicilio' => $cotizacion->direccion ?? null,
                    'carga' => $carga,
                    'logo_contrato_url' => public_path('storage/logo_contrato.png'),
                    'signature_base64' => $signatureBase64,
                    'cod_contract' => $cotizacion->cod_contract,
                ];

                // Renderizar vista del contrato con firma
                $contractHtml = view('contracts.contrato_firmado', $viewData)->render();

                // Configurar DomPDF
                if (function_exists('set_time_limit')) {
                    @set_time_limit(120);
                }
                ini_set('memory_limit', '512M');

                $options = new \Dompdf\Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('defaultFont', 'DejaVu Sans');

                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->loadHtml($contractHtml);
                $dompdf->setPaper('A4', 'portrait');

                Log::info('Iniciando renderizado PDF firmado para cotizacion ' . $cotizacion->id);
                $start = microtime(true);
                $dompdf->render();
                $duration = microtime(true) - $start;
                Log::info('Renderizado PDF firmado completado en ' . round($duration, 2) . 's para cotizacion ' . $cotizacion->id);

                $pdfContent = $dompdf->output();

                // Guardar PDF
                file_put_contents($pdfPath, $pdfContent);

                // Usar el nombre del PDF
                $filename = $pdfFilename;
                $relativePath = 'contratos/' . $filename;
            } else {
                // Si ya es PDF, mover directamente
                $filename = $uuid . '_signed_contract.pdf';
                $filePath = $contratosDir . '/' . $filename;

                // Eliminar archivo anterior si existe
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                $signedFile->move($contratosDir, $filename);
                $relativePath = 'contratos/' . $filename;
            }

            // Guardar ruta relativa en la base de datos
            $cotizacion->cotizacion_contrato_firmado_url = $relativePath;
            $cotizacion->save();
            // Enviar mensaje de WhatsApp con el contrato firmado
            $telefono = $cotizacion->telefono;
            $telefono = preg_replace('/\s+/', '', $telefono);
            $telefono = $telefono ? $telefono . '@c.us' : '';

            $message = 'Hola ' . $cotizacion->nombre . ', te envío el contrato de servicio firmado.';

            // Usar la ruta correcta del archivo (PDF generado o archivo movido)
            $finalFilePath = $contratosDir . '/' . $filename;
            $fileName = 'contrato_' . $cotizacion->cod_contract . '.pdf';
            $this->sendMedia($finalFilePath, 'application/pdf', $message, $telefono, 10, 'ventas', $fileName);

            Log::info('Contrato firmado guardado exitosamente', [
                'uuid' => $uuid,
                'filename' => $filename,
                'cotizacion_id' => $cotizacion->id,
                'mime_type' => $mimeType,
                'is_image' => $isImage,
                'relative_path' => $relativePath,
                'final_file_path' => $finalFilePath
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contrato de servicio firmado correctamente',
                'data' => [
                    'signed_contract_url' => $this->generateImageUrl($relativePath)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al firmar contrato: ' . $e->getMessage(), [
                'uuid' => $uuid,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el contrato firmado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crea notificaciones para Coordinación y Cotizador cuando se contacta a un proveedor en China
     */
    private function crearNotificacionesProveedorContactado($cotizacion, $proveedor, $supplierCode, $carga, $arriveDate, $usuarioActual)
    {
        try {
            if (!$usuarioActual) {
                Log::warning('Usuario actual no encontrado al contactar proveedor en China');
                return;
            }

            // Crear la notificación para Coordinación
            $rolCoordinacion = Usuario::ROL_COORDINACION;
            Log::info('Creando notificación para Coordinación', [
                'rol_coordinacion' => $rolCoordinacion,
                'rol_constante' => Usuario::ROL_COORDINACION,
                'supplier_code' => $supplierCode,
                'cotizacion_id' => $cotizacion->id
            ]);
            
            $notificacionCoordinacion = Notificacion::create([
                'titulo' => 'Proveedor Contactado en China',
                'mensaje' => "El usuario {$usuarioActual->No_Nombres_Apellidos} contactó al proveedor con código {$supplierCode} del cliente {$cotizacion->nombre}",
                'descripcion' => "Cliente: {$cotizacion->nombre} | Código Proveedor: {$supplierCode} | Contenedor: #{$carga} | Fecha de llegada: {$arriveDate}",
                'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                'rol_destinatario' => Usuario::ROL_COORDINACION,
                'navigate_to' => 'cargaconsolidada/abiertos/cotizaciones',
                'navigate_params' => json_encode([
                    'idContenedor' => $cotizacion->id_contenedor,
                    'tab' => 'prospectos',
                    'idCotizacion' => $cotizacion->id
                ]),
                'tipo' => Notificacion::TIPO_INFO,
                'icono' => 'mdi:phone-outgoing',
                'prioridad' => Notificacion::PRIORIDAD_MEDIA,
                'referencia_tipo' => 'proveedor',
                'referencia_id' => $proveedor->id,
                'activa' => true,
                'creado_por' => $usuarioActual->ID_Usuario,
                'configuracion_roles' => json_encode([
                    Usuario::ROL_COORDINACION => [
                        'titulo' => 'Proveedor Contactado - China',
                        'mensaje' => "Proveedor {$supplierCode} contactado del cliente {$cotizacion->nombre}",
                        'descripcion' => "Fecha de llegada: {$arriveDate} | Contenedor: #{$carga}"
                    ]
                ])
            ]);

            // Crear la notificación para Cotizador
            $notificacionCotizador = Notificacion::create([
                'titulo' => 'Proveedor Contactado en China',
                'mensaje' => "El usuario {$usuarioActual->No_Nombres_Apellidos} contactó al proveedor con código {$supplierCode} del cliente {$cotizacion->nombre}",
                'descripcion' => "Cliente: {$cotizacion->nombre} | Código Proveedor: {$supplierCode} | Contenedor: #{$carga} | Fecha de llegada: {$arriveDate}",
                'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                'rol_destinatario' => Usuario::ROL_COTIZADOR,
                'navigate_to' => 'cargaconsolidada/abiertos/cotizaciones',
                'navigate_params' => json_encode([
                    'idContenedor' => $cotizacion->id_contenedor,
                    'tab' => 'prospectos',
                    'idCotizacion' => $cotizacion->id
                ]),
                'tipo' => Notificacion::TIPO_INFO,
                'icono' => 'mdi:phone-outgoing',
                'prioridad' => Notificacion::PRIORIDAD_MEDIA,
                'referencia_tipo' => 'proveedor',
                'referencia_id' => $proveedor->id,
                'activa' => true,
                'creado_por' => $usuarioActual->ID_Usuario,
                'configuracion_roles' => json_encode([
                    Usuario::ROL_COTIZADOR => [
                        'titulo' => 'Proveedor Contactado - China',
                        'mensaje' => "Proveedor {$supplierCode} contactado del cliente {$cotizacion->nombre}",
                        'descripcion' => "Fecha de llegada: {$arriveDate} | Contenedor: #{$carga}"
                    ]
                ])
            ]);
            //notificar tambien al jefe de ventas
            $notificacionJefeVentas = Notificacion::create([
                'titulo' => 'Proveedor Contactado en China',
                'mensaje' => "El usuario {$usuarioActual->No_Nombres_Apellidos} contactó al proveedor con código {$supplierCode} del cliente {$cotizacion->nombre}",
                'descripcion' => "Cliente: {$cotizacion->nombre} | Código Proveedor: {$supplierCode} | Contenedor: #{$carga} | Fecha de llegada: {$arriveDate}",
                'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                'usuario_destinatario' => Usuario::ID_JEFE_VENTAS,
                'rol_destinatario' => Usuario::ROL_COTIZADOR,
                'navigate_to' => 'cargaconsolidada/abiertos/cotizaciones',
                'navigate_params' => json_encode([
                    'idContenedor' => $cotizacion->id_contenedor,
                    'tab' => 'prospectos',
                    'idCotizacion' => $cotizacion->id
                ]),
                'tipo' => Notificacion::TIPO_INFO,
                'icono' => 'mdi:phone-outgoing',
                'prioridad' => Notificacion::PRIORIDAD_MEDIA,
                'referencia_tipo' => 'proveedor',
                'referencia_id' => $proveedor->id,
                'activa' => true,
                'creado_por' => $usuarioActual->ID_Usuario,
                'configuracion_roles' => json_encode([
                    Usuario::ROL_COTIZADOR => [
                        'titulo' => 'Proveedor Contactado - China',
                        'mensaje' => "Proveedor {$supplierCode} contactado del cliente {$cotizacion->nombre}",
                        'descripcion' => "Fecha de llegada: {$arriveDate} | Contenedor: #{$carga}"
                    ]
                ])
            ]);

            Log::info('Notificaciones de proveedor contactado en China creadas para Coordinación y Cotizador:', [
                'notificacion_coordinacion_id' => $notificacionCoordinacion->id,
                'notificacion_coordinacion_rol_destinatario' => $notificacionCoordinacion->rol_destinatario,
                'notificacion_coordinacion_rol_destinatario_encoded' => base64_encode($notificacionCoordinacion->rol_destinatario),
                'notificacion_coordinacion_rol_constante' => Usuario::ROL_COORDINACION,
                'notificacion_coordinacion_rol_constante_encoded' => base64_encode(Usuario::ROL_COORDINACION),
                'notificacion_coordinacion_coinciden' => $notificacionCoordinacion->rol_destinatario === Usuario::ROL_COORDINACION,
                'notificacion_coordinacion_usuario_destinatario' => $notificacionCoordinacion->usuario_destinatario,
                'notificacion_coordinacion_activa' => $notificacionCoordinacion->activa,
                'notificacion_cotizador_id' => $notificacionCotizador->id,
                'notificacion_cotizador_rol_destinatario' => $notificacionCotizador->rol_destinatario,
                'notificacion_cotizador_usuario_destinatario' => $notificacionCotizador->usuario_destinatario,
                'notificacion_cotizador_activa' => $notificacionCotizador->activa,
                'cotizacion_id' => $cotizacion->id,
                'proveedor_id' => $proveedor->id,
                'supplier_code' => $supplierCode,
                'usuario_actual' => $usuarioActual->No_Nombres_Apellidos
            ]);
            
            // Verificar que la notificación de Coordinación se guardó correctamente
            $verificacionCoordinacion = Notificacion::find($notificacionCoordinacion->id);
            Log::info('Verificación de notificación de Coordinación en BD:', [
                'existe' => $verificacionCoordinacion !== null,
                'id' => $verificacionCoordinacion ? $verificacionCoordinacion->id : null,
                'rol_destinatario' => $verificacionCoordinacion ? $verificacionCoordinacion->rol_destinatario : null,
                'activa' => $verificacionCoordinacion ? $verificacionCoordinacion->activa : null
            ]);

            return [$notificacionCoordinacion, $notificacionCotizador];
        } catch (\Exception $e) {
            Log::error('Error al crear notificaciones de proveedor contactado en China', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            // No lanzar excepción para no afectar el flujo principal
            return null;
        }
    }

    /**
     * Dispara el evento CotizacionChinaReceived y crea notificaciones para Coordinación y Cotizador
     */
    private function dispararEventoYNotificacionProveedorRecibido($cotizacion, $proveedor, $supplierCode, $qtyBox, $cbmTotal, $carga, $usuarioActual)
    {
        try {
            if (!$usuarioActual) {
                Log::warning('Usuario actual no encontrado al recibir proveedor en China');
                return;
            }

            // Disparar evento
            try {
                $message = "El usuario {$usuarioActual->No_Nombres_Apellidos} recibió el proveedor con código {$supplierCode} del cliente {$cotizacion->nombre} en el contenedor {$carga}";
                CotizacionChinaReceived::dispatch($cotizacion, $proveedor, $supplierCode, $qtyBox, $cbmTotal, $message);
            } catch (\Exception $e) {
                Log::error('Error al disparar evento CotizacionChinaReceived: ' . $e->getMessage());
            }

            // Crear notificaciones en la base de datos
            $this->crearNotificacionesProveedorRecibido($cotizacion, $proveedor, $supplierCode, $qtyBox, $cbmTotal, $carga, $usuarioActual);
        } catch (\Exception $e) {
            Log::error('Error al disparar evento y crear notificaciones de proveedor recibido en China: ' . $e->getMessage());
        }
    }

    /**
     * Crea notificaciones para Coordinación y Cotizador cuando se recibe un proveedor en China
     */
    private function crearNotificacionesProveedorRecibido($cotizacion, $proveedor, $supplierCode, $qtyBox, $cbmTotal, $carga, $usuarioActual)
    {
        try {
            if (!$usuarioActual) {
                Log::warning('Usuario actual no encontrado al recibir proveedor en China');
                return;
            }

            // Crear la notificación para Coordinación
            $notificacionCoordinacion = Notificacion::create([
                'titulo' => 'Proveedor Recibido en China',
                'mensaje' => "El usuario {$usuarioActual->No_Nombres_Apellidos} recibió el proveedor con código {$supplierCode} del cliente {$cotizacion->nombre}",
                'descripcion' => "Cliente: {$cotizacion->nombre} | Código Proveedor: {$supplierCode} | Contenedor: #{$carga} | Cajas: {$qtyBox} | Volumen: {$cbmTotal} m³",
                'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                'rol_destinatario' => Usuario::ROL_COORDINACION,
                'navigate_to' => 'cargaconsolidada/abiertos/cotizaciones',
                'navigate_params' => json_encode([
                    'idContenedor' => $cotizacion->id_contenedor,
                    'tab' => 'prospectos',
                    'idCotizacion' => $cotizacion->id
                ]),
                'tipo' => Notificacion::TIPO_SUCCESS,
                'icono' => 'mdi:package-variant',
                'prioridad' => Notificacion::PRIORIDAD_MEDIA,
                'referencia_tipo' => 'proveedor',
                'referencia_id' => $proveedor->id,
                'activa' => true,
                'creado_por' => $usuarioActual->ID_Usuario,
                'configuracion_roles' => json_encode([
                    Usuario::ROL_COORDINACION => [
                        'titulo' => 'Proveedor Recibido - China',
                        'mensaje' => "Proveedor {$supplierCode} recibido del cliente {$cotizacion->nombre}",
                        'descripcion' => "Cajas: {$qtyBox} | Volumen: {$cbmTotal} m³ | Contenedor: #{$carga}"
                    ]
                ])
            ]);

            // Crear la notificación para Cotizador
            $notificacionCotizador = Notificacion::create([
                'titulo' => 'Proveedor Recibido en China',
                'mensaje' => "El usuario {$usuarioActual->No_Nombres_Apellidos} recibió el proveedor con código {$supplierCode} del cliente {$cotizacion->nombre}",
                'descripcion' => "Cliente: {$cotizacion->nombre} | Código Proveedor: {$supplierCode} | Contenedor: #{$carga} | Cajas: {$qtyBox} | Volumen: {$cbmTotal} m³",
                'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                'rol_destinatario' => Usuario::ROL_COTIZADOR,
                'navigate_to' => 'cargaconsolidada/abiertos/cotizaciones',
                'navigate_params' => json_encode([
                    'idContenedor' => $cotizacion->id_contenedor,
                    'tab' => 'prospectos',
                    'idCotizacion' => $cotizacion->id
                ]),
                'tipo' => Notificacion::TIPO_SUCCESS,
                'icono' => 'mdi:package-variant',
                'prioridad' => Notificacion::PRIORIDAD_MEDIA,
                'referencia_tipo' => 'proveedor',
                'referencia_id' => $proveedor->id,
                'activa' => true,
                'creado_por' => $usuarioActual->ID_Usuario,
                'configuracion_roles' => json_encode([
                    Usuario::ROL_COTIZADOR => [
                        'titulo' => 'Proveedor Recibido - China',
                        'mensaje' => "Proveedor {$supplierCode} recibido del cliente {$cotizacion->nombre}",
                        'descripcion' => "Cajas: {$qtyBox} | Volumen: {$cbmTotal} m³ | Contenedor: #{$carga}"
                    ]
                ])
            ]);

            Log::info('Notificaciones de proveedor recibido en China creadas para Coordinación y Cotizador:', [
                'notificacion_coordinacion_id' => $notificacionCoordinacion->id,
                'notificacion_cotizador_id' => $notificacionCotizador->id,
                'cotizacion_id' => $cotizacion->id,
                'proveedor_id' => $proveedor->id,
                'supplier_code' => $supplierCode
            ]);

            return [$notificacionCoordinacion, $notificacionCotizador];
        } catch (\Exception $e) {
            Log::error('Error al crear notificaciones de proveedor recibido en China: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Dispara el evento CotizacionChinaInspected y crea notificaciones para Coordinación y Cotizador
     */
    private function dispararEventoYNotificacionProveedorInspeccionado($cotizacion, $proveedor, $supplierCode, $carga, $usuarioActual)
    {
        try {
            if (!$usuarioActual) {
                Log::warning('Usuario actual no encontrado al inspeccionar proveedor en China');
                return;
            }

            // Disparar evento
            try {
                $message = "El usuario {$usuarioActual->No_Nombres_Apellidos} inspeccionó el proveedor con código {$supplierCode} del cliente {$cotizacion->nombre} en el contenedor {$carga}";
                CotizacionChinaInspected::dispatch($cotizacion, $proveedor, $supplierCode, $message);
            } catch (\Exception $e) {
                Log::error('Error al disparar evento CotizacionChinaInspected: ' . $e->getMessage());
            }

            // Crear notificaciones en la base de datos
            $this->crearNotificacionesProveedorInspeccionado($cotizacion, $proveedor, $supplierCode, $carga, $usuarioActual);
        } catch (\Exception $e) {
            Log::error('Error al disparar evento y crear notificaciones de proveedor inspeccionado en China: ' . $e->getMessage());
        }
    }

    /**
     * Crea notificaciones para Coordinación y Cotizador cuando se inspecciona un proveedor en China
     */
    private function crearNotificacionesProveedorInspeccionado($cotizacion, $proveedor, $supplierCode, $carga, $usuarioActual)
    {
        try {
            if (!$usuarioActual) {
                Log::warning('Usuario actual no encontrado al inspeccionar proveedor en China');
                return;
            }

            // Crear la notificación para Coordinación
            $notificacionCoordinacion = Notificacion::create([
                'titulo' => 'Proveedor Inspeccionado en China',
                'mensaje' => "El usuario {$usuarioActual->No_Nombres_Apellidos} inspeccionó el proveedor con código {$supplierCode} del cliente {$cotizacion->nombre}",
                'descripcion' => "Cliente: {$cotizacion->nombre} | Código Proveedor: {$supplierCode} | Contenedor: #{$carga}",
                'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                'rol_destinatario' => Usuario::ROL_COORDINACION,
                'navigate_to' => 'cargaconsolidada/abiertos/cotizaciones',
                'navigate_params' => json_encode([
                    'idContenedor' => $cotizacion->id_contenedor,
                    'tab' => 'prospectos',
                    'idCotizacion' => $cotizacion->id
                ]),
                'tipo' => Notificacion::TIPO_INFO,
                'icono' => 'mdi:clipboard-check',
                'prioridad' => Notificacion::PRIORIDAD_MEDIA,
                'referencia_tipo' => 'proveedor',
                'referencia_id' => $proveedor->id,
                'activa' => true,
                'creado_por' => $usuarioActual->ID_Usuario,
                'configuracion_roles' => json_encode([
                    Usuario::ROL_COORDINACION => [
                        'titulo' => 'Proveedor Inspeccionado - China',
                        'mensaje' => "Proveedor {$supplierCode} inspeccionado del cliente {$cotizacion->nombre}",
                        'descripcion' => "Contenedor: #{$carga}"
                    ]
                ])
            ]);

            // Crear la notificación para Cotizador
            $notificacionCotizador = Notificacion::create([
                'titulo' => 'Proveedor Inspeccionado en China',
                'mensaje' => "El usuario {$usuarioActual->No_Nombres_Apellidos} inspeccionó el proveedor con código {$supplierCode} del cliente {$cotizacion->nombre}",
                'descripcion' => "Cliente: {$cotizacion->nombre} | Código Proveedor: {$supplierCode} | Contenedor: #{$carga}",
                'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                'rol_destinatario' => Usuario::ROL_COTIZADOR,
                'navigate_to' => 'cargaconsolidada/abiertos/cotizaciones',
                'navigate_params' => json_encode([
                    'idContenedor' => $cotizacion->id_contenedor,
                    'tab' => 'prospectos',
                    'idCotizacion' => $cotizacion->id
                ]),
                'tipo' => Notificacion::TIPO_INFO,
                'icono' => 'mdi:clipboard-check',
                'prioridad' => Notificacion::PRIORIDAD_MEDIA,
                'referencia_tipo' => 'proveedor',
                'referencia_id' => $proveedor->id,
                'activa' => true,
                'creado_por' => $usuarioActual->ID_Usuario,
                'configuracion_roles' => json_encode([
                    Usuario::ROL_COTIZADOR => [
                        'titulo' => 'Proveedor Inspeccionado - China',
                        'mensaje' => "Proveedor {$supplierCode} inspeccionado del cliente {$cotizacion->nombre}",
                        'descripcion' => "Contenedor: #{$carga}"
                    ]
                ])
            ]);

            Log::info('Notificaciones de proveedor inspeccionado en China creadas para Coordinación y Cotizador:', [
                'notificacion_coordinacion_id' => $notificacionCoordinacion->id,
                'notificacion_cotizador_id' => $notificacionCotizador->id,
                'cotizacion_id' => $cotizacion->id,
                'proveedor_id' => $proveedor->id,
                'supplier_code' => $supplierCode
            ]);

            return [$notificacionCoordinacion, $notificacionCotizador];
        } catch (\Exception $e) {
            Log::error('Error al crear notificaciones de proveedor inspeccionado en China: ' . $e->getMessage());
            return null;
        }
    }
}
