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
use App\Traits\WhatsappTrait;

class CotizacionProveedorController extends Controller
{
    use WhatsappTrait;
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
            $page = $request->currentPage ?? 1;
            $perPage = $request->itemsPerPage ?? 10;
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
                ->where('main.id_contenedor', $idContenedor)
                ->whereNull('id_cliente_importacion');


            if (!empty($search)) {
                Log::info('search: ' . $search);
                $query->where('main.nombre', 'LIKE', '%' . $search . '%');
            }
            if ($request->has('estado_coordinacion') || $request->has('estado_china')) {
                // Como estamos usando el constructor de consultas de Laravel (DB::table), no se puede usar whereHas directamente.
                // En su lugar, hacemos un whereExists para filtrar cotizaciones que tengan al menos un proveedor con el estado requerido.
                $query->whereExists(function ($sub) use ($request) {
                    $sub->select(DB::raw(1))
                        ->from('contenedor_consolidado_cotizacion_proveedores as proveedores')
                        ->whereRaw('proveedores.id_cotizacion = main.id');
                    if ($request->has('estado_coordinacion') && $request->estado_coordinacion != 'todos') {
                        $sub->where('proveedores.estados', $request->estado_coordinacion);
                    }
                    if ($request->has('estado_china') && $request->estado_china != 'todos') {
                        $sub->where('proveedores.estados_proveedor', $request->estado_china);
                    }
                });
            }
            $query->orderBy('main.id', 'asc');
            
            if ($rol != Usuario::ROL_COTIZADOR) {
                $query->where('estado_cotizador', 'CONFIRMADO');
            } else if ($rol == Usuario::ROL_COTIZADOR && $user->ID_Usuario != 28791) {
                $query->where('main.id_usuario', $user->ID_Usuario);
                $query->orderBy('main.id', 'desc');
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
            else if (in_array($estado, ["NC", "C", "R", "NS", "NO LOADED", "INSPECTION"])) {
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

            // Actualizar timestamp en tracking
            DB::table($this->table_conteneodr_proveedor_estados_tracking)
                ->where('id_proveedor', $idProveedor)
                ->update(['updated_at' => now()]);



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
     * Actualizar datos del proveedor
     */
    public function updateProveedorData(Request $request)
    {
        try {
            $idProveedor = $request->id;
            Log::info('idProveedor: ' . $idProveedor);
            $data = $request->all();
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
                    $proveedor->supplier_phone = $data['supplier_phone'] ?? null;
                    $proveedor->supplier = $data['supplier'] ?? null;
                    $proveedor->save();
                }


                $this->verifyContainerIsCompleted($idContenedor);
            }

            if (
                isset($data['arrive_date_china']) &&
                (!isset($data['qty_box_china']) && !isset($data['cbm_total_china']))
            ) {
                $data['arrive_date_china'] = date('Y-m-d', strtotime(str_replace('/', '-', $data['arrive_date_china'])));
                $estadoProveedorOrder = $this->providerOrderStatus[$estadoProveedor] ?? 0;
                $estadoProvedorToUpdate = $this->providerOrderStatus[$this->STATUS_CONTACTED] ?? 0;
                if ($estadoProveedorOrder < $estadoProvedorToUpdate) {
                    $proveedor->estados = $this->STATUS_CONTACTED;
                    $proveedor->arrive_date_china = $data['arrive_date_china'];
                    $proveedor->save();


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
                }
                $this->verifyContainerIsCompleted($idContenedor);
            }
            if (isset($data['qty_box_china']) && isset($data['cbm_total_china'])) {
                $dateTime = \DateTime::createFromFormat('d/m/Y', $data['arrive_date_china']);
                if ($dateTime) {
                    $data['arrive_date_china'] = $dateTime->format('Y-m-d');
                }
                $estadoProveedorOrder = $this->providerOrderStatus[$estadoProveedor] ?? 0;
                $estadoProvedorToUpdate = $this->providerOrderStatus[$this->STATUS_RECIVED] ?? 0;
                if ($estadoProveedorOrder < $estadoProvedorToUpdate) {
                    $proveedor->estados_proveedor = $this->STATUS_RECIVED;
                    $proveedor->qty_box_china = $data['qty_box_china'];
                    $proveedor->cbm_total_china = $data['cbm_total_china'];
                    $proveedor->arrive_date_china = $data['arrive_date_china'];
                    $proveedor->save();


                    $proveedor->estados = $this->STATUS_RECIVED;
                    $proveedor->save();


                    //update estado column in table $this->table to estado RECIBIENDO where id = $idContenedor




                    $usuariosAlmacen = $this->getUsersByGrupo(Usuario::ROL_COORDINACION);
                    $ids = array_column($usuariosAlmacen, 'ID_Usuario');
                    $message = "Se ha actualizado el proveedor con codigo de proveedor " . $supplierCode . " a estado RECIBIDO";
                    $notifications = $this->createNotification($ids, $message, "CARGA CONSOLIDADA", $user->ID_Usuario);

                    // ]);
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
            $proveedor->update($data);
            $volumenChina = CotizacionProveedor::where('id_cotizacion', $idCotizacion)
                ->where('estados_proveedor', "LOADED")
                ->sum('cbm_total_china');

            $cotizacion = Cotizacion::find($idCotizacion);
            $cotizacion->volumen_china = $volumenChina;
            $cotizacion->save();
            $this->verifyContainerIsCompleted($idContenedor);
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
            $contenedor = DB::table('carga_consolidada_contenedor')->where('id', $cotizacion->id_contenedor)->first();
            $fCierre = $contenedor ? $contenedor->f_cierre : null;

            // Formatear fecha de cierre
            if ($fCierre) {
                $fCierre = Carbon::parse($fCierre)->format('d F');
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
                $this->sendMessage($message, $telefono);
            }

            // Filtrar archivos pendientes de envío
            $imagesPendientes = $imagesUrls->where('send_status', 'PENDING');
            $videosPendientes = $videosUrls->where('send_status', 'PENDING');

            // Simular envío de medios de inspección
            foreach ($imagesPendientes as $image) {
                // Usar la ruta del sistema de archivos, no la URL
                $fileSystemPath = storage_path('app/public/' . $image->file_path);
                Log::info('Enviando imagen de inspección. Ruta del sistema: ' . $fileSystemPath);

                // Verificar que el archivo existe
                if (file_exists($fileSystemPath)) {
                    $this->sendMediaInspection($fileSystemPath, $image->file_type, $message, $telefono, 0, $image->id);
                } else {
                    Log::error('Archivo no encontrado en el sistema: ' . $fileSystemPath);
                }
            }

            foreach ($videosPendientes as $video) {
                // Usar la ruta del sistema de archivos, no la URL
                $fileSystemPath = storage_path('app/public/' . $video->file_path);
                Log::info('Enviando video de inspección. Ruta del sistema: ' . $fileSystemPath);

                // Verificar que el archivo existe
                if (file_exists($fileSystemPath)) {
                    $this->sendMediaInspection($fileSystemPath, $video->file_type, $message, $telefono, 0, $video->id);
                } else {
                    Log::error('Archivo no encontrado en el sistema: ' . $fileSystemPath);
                }
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

            Log::info('Archivos de inspección recibidos:', ['cantidad' => count($files)]);

            $archivosGuardados = [];

            foreach ($files as $file) {
                if ($file->isValid()) {
                    // Generar nombre único para el archivo
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

                    // Guardar archivo en storage
                    $path = $file->storeAs(self::INSPECTION_PATH, $filename, 'public');

                    // Crear registro en la base de datos
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
            $this->validateToSendInspectionMessage($idProveedor);
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
    public function getCotizacionProveedor($idProveedor)
    {
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
}
