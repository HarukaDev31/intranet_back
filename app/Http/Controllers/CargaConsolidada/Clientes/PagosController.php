<?php

namespace App\Http\Controllers\CargaConsolidada\Clientes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Usuario;
use App\Models\Notificacion;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PagosController extends Controller
{
    private $table_contenedor_cotizacion = "contenedor_consolidado_cotizacion";
    private $table_contenedor_tipo_cliente = "contenedor_consolidado_tipo_cliente";
    private $table_pagos_concept = "cotizacion_coordinacion_pagos_concept";
    private $table_contenedor_consolidado_cotizacion_coordinacion_pagos = "contenedor_consolidado_cotizacion_coordinacion_pagos";

    // Constantes para conceptos de pago
    private $CONCEPT_PAGO_LOGISTICA = 1;
    private $CONCEPT_PAGO_IMPUESTO = 2;


    public function index(Request $request, $idContenedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            //get as subquery json of pagos_details
            $query = DB::table('contenedor_consolidado_cotizacion as CC')
                ->select(
                    "*",
                    "CC.id AS id_cotizacion",
                    DB::raw("(
                SELECT IFNULL(SUM(cccp.monto), 0) 
                FROM " . $this->table_contenedor_consolidado_cotizacion_coordinacion_pagos . " cccp
                JOIN " . $this->table_pagos_concept . " ccp ON cccp.id_concept= ccp.id
                WHERE cccp.id_cotizacion = CC.id
                AND ccp.name = 'LOGISTICA'
            ) AS total_pagos"),
                    DB::raw("(
                SELECT COUNT(*) 
                FROM " . $this->table_contenedor_consolidado_cotizacion_coordinacion_pagos . " cccp
                JOIN " . $this->table_pagos_concept . " ccp ON cccp.id_concept = ccp.id
                WHERE cccp.id_cotizacion = CC.id
                AND ccp.name = 'LOGISTICA'
            ) AS pagos_count"),
                    DB::raw("(
                SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'id_pago', cccp2.id,
                        'monto', cccp2.monto,
                        'concepto', ccp2.name,
                        'status', cccp2.status,
                        'payment_date', cccp2.payment_date,
                        'banco', cccp2.banco,
                        'voucher_url', cccp2.voucher_url
                    )
                ) FROM " . $this->table_contenedor_consolidado_cotizacion_coordinacion_pagos . " cccp2
                JOIN " . $this->table_pagos_concept . " ccp2 ON cccp2.id_concept = ccp2.id
                WHERE cccp2.id_cotizacion = CC.id
                AND ccp2.name = 'LOGISTICA'
            ) as pagos_details")
                )
                ->leftJoin($this->table_contenedor_tipo_cliente . ' AS TC', 'TC.id', '=', 'CC.id_tipo_cliente')
                ->where('CC.id_contenedor', $idContenedor)
                ->whereNull('CC.id_cliente_importacion')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('contenedor_consolidado_cotizacion_proveedores')
                        ->whereColumn('contenedor_consolidado_cotizacion_proveedores.id_cotizacion', 'CC.id');
                })
                ->where('CC.estado_cotizador', 'CONFIRMADO')
                ->where('CC.estado_cliente', 'CONFIRMADO')
                ->orderBy('CC.id', 'asc');
            // Si el usuario es "Cotizador", filtrar por el id del usuario actual
            if ($user->getNombreGrupo() == Usuario::ROL_COTIZADOR && $user->ID_Usuario != 28791) {
                $query->where($this->table_contenedor_cotizacion . '.id_usuario', $user->ID_Usuario);
                //order by fecha_confirmacion asc

            }
            if ($user->getNombreGrupo() != Usuario::ROL_COTIZADOR) {
                $query->where('estado_cotizador', 'CONFIRMADO');
            }
            if ($user->getNombreGrupo() == Usuario::ROL_COTIZADOR) {
                $query->orderBy('fecha_confirmacion', 'asc');
            }

            // Paginación
            $perPage = $request->get('limit', 100);
            $page = $request->get('page', 1);
            $query = $query->paginate($perPage, ['*'], 'page', $page);
            $items = $query->items();
            foreach ($items as $item) {
                $pagosDetails = json_decode($item->pagos_details ?? '[]', true);
                foreach ($pagosDetails as &$pago) {
                    if ($pago['voucher_url']) {
                        Log::info('Voucher URL original: ' . $pago['voucher_url']);
                        $pago['voucher_url'] = $this->generateImageUrl($pago['voucher_url']);
                        Log::info('Voucher URL modificada: ' . $pago['voucher_url']);
                    }
                    
                }
                $item->pagos_details = json_encode($pagosDetails);
                $estadoPago = 'PENDIENTE';
                    if ($item->pagos_count == 0) {
                        $estadoPago = 'PENDIENTE';
                    } else if ($item->total_pagos < $item->monto) {
                        $estadoPago = 'ADELANTO';
                    } else if ($item->total_pagos == $item->monto) {
                        $estadoPago = 'PAGADO';
                    } else if ($item->total_pagos > $item->monto) {
                        $estadoPago = 'SOBREPAGO';
                    }
                $item->estado_pago = $estadoPago;
            }
            return response()->json([
                'success' => true,
                'data' => $items,
                'pagination' => [
                    'current_page' => $query->currentPage(),
                    'last_page' => $query->lastPage(),
                    'per_page' => $query->perPage(),
                    'total' => $query->total()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en index: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener los pagos: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Guardar pago de coordinación del cliente
     */
    public function store(Request $request)
    {
        try {
            // Validar los datos de entrada
            $request->validate([
                'voucher' => 'required|file',
                'idCotizacion' => 'required|integer',
                'idContenedor' => 'required|integer',
                'monto' => 'required|numeric|min:0',
                'fecha' => 'required|date',
                'banco' => 'required|string|max:255'
            ]);

            // Autenticar usuario
            $user = JWTAuth::parseToken()->authenticate();

            // Subir el archivo voucher
            $voucherUrl = null;
            if ($request->hasFile('voucher')) {
                $file = $request->file('voucher');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $voucherUrl = $file->storeAs('cargaconsolidada/pagos', $fileName, 'public');
            }

            // Verificar si la cotización tiene cotizacion_final_url
            $cotizacion = DB::table($this->table_contenedor_cotizacion)
                ->select('cotizacion_final_url')
                ->where('id', $request->idCotizacion)
                ->first();

            $cotizacionFinalUrl = $cotizacion ? $cotizacion->cotizacion_final_url : null;

            if ($cotizacion) {
                Log::info('Cotizacion Final URL: ' . $cotizacion->cotizacion_final_url);
            }

            // Determinar el concepto de pago
            $conceptId =  $this->CONCEPT_PAGO_LOGISTICA;

            // Preparar datos para insertar
            $data = [
                'voucher_url' => $voucherUrl,
                'id_cotizacion' => $request->idCotizacion,
                'id_contenedor' => $request->idContenedor,
                'id_concept' => $conceptId,
                'monto' => $request->monto,
                'payment_date' => date('Y-m-d', strtotime($request->fecha)),
                'banco' => $request->banco,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Insertar en la base de datos
            $inserted = DB::table($this->table_contenedor_consolidado_cotizacion_coordinacion_pagos)->insert($data);

            if ($inserted) {
                // Obtener información del cliente y contenedor para la notificación
                $cotizacionInfo = DB::table($this->table_contenedor_cotizacion . ' as CC')
                    ->join('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
                    ->select('CC.nombre as cliente_nombre', 'CC.documento as cliente_documento', 'C.carga as contenedor_nombre')
                    ->where('CC.id', $request->idCotizacion)
                    ->first();

                if ($cotizacionInfo) {
                    Notificacion::create([
                        'titulo' => 'Nuevo Pago de Logística Registrado',
                        'mensaje' => "Se ha registrado un pago de logística de $ {$request->monto} para el cliente {$cotizacionInfo->cliente_nombre} del contenedor {$cotizacionInfo->contenedor_nombre}",
                        'descripcion' => "Cliente: {$cotizacionInfo->cliente_nombre} | Documento: {$cotizacionInfo->cliente_documento} | Monto: S/ {$request->monto} | Banco: {$request->banco} | Fecha: {$request->fecha}",
                        'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                        'rol_destinatario' => Usuario::ROL_ADMINISTRACION,
                        'navigate_to' => 'verificacion',
                        'navigate_params' => [
                            'idCotizacion' => $request->idCotizacion,
                            'tab' => 'consolidado'
                        ],
                        'tipo' => Notificacion::TIPO_SUCCESS,
                        'icono' => 'mdi:cash-check',
                        'prioridad' => Notificacion::PRIORIDAD_ALTA,
                        'referencia_tipo' => 'pago_logistica',
                        'referencia_id' => $request->idCotizacion,
                        'activa' => true,
                        'creado_por' => $user->ID_Usuario,
                        'configuracion_roles' => [
                            Usuario::ROL_ADMINISTRACION => [
                                'titulo' => 'Pago Logística - Verificar',
                                'mensaje' => "Nuevo pago de $ {$request->monto} para verificar",
                                'descripcion' => "Cliente: {$cotizacionInfo->cliente_nombre} | Contenedor: {$cotizacionInfo->contenedor_nombre}"
                            ]
                        ]
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Pago guardado exitosamente',
                    'data' => $data
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al guardar el pago en la base de datos'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el pago: ' . $e->getMessage()
            ], 500);
        }
    }
    public function delete($id)
    {
        try {
            $pago = DB::table($this->table_contenedor_consolidado_cotizacion_coordinacion_pagos)->where('id', $id)->delete();
            return response()->json(['success' => true, 'message' => 'Pago eliminado exitosamente']);
        } catch (\Exception $e) {
            Log::error('Error en delete: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al eliminar el pago: ' . $e->getMessage()], 500);
        }
    }
    public function generateImageUrl($ruta)
    {
        Log::info('Ruta: ' . $ruta);
        if (empty($ruta)) {
            return null;
        }
        if(strpos($ruta, 'http') === 0){
            return $ruta;
        }
       
        Log::info('Ruta: ' . $ruta);
        // Generar URL completa desde storage
        return Storage::disk('public')->url($ruta);
    }
}
