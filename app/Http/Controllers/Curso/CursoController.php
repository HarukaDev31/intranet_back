<?php

namespace App\Http\Controllers\Curso;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PedidoCurso;
use App\Models\Campana;
use App\Models\Usuario;
use App\Models\Notificacion;
use App\Helpers\DateHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Traits\CodeIgniterEncryption;
use App\Traits\MoodleRestProTrait;
use App\Traits\FileTrait;
use App\Traits\WhatsappTrait;
use App\Mail\MoodleCredentialsMail;
use App\Exports\CursosExport;
use Maatwebsite\Excel\Facades\Excel;

class CursoController extends Controller
{
    use CodeIgniterEncryption, MoodleRestProTrait, FileTrait, WhatsappTrait;
    public $table_pedido_curso_pagos = 'pedido_curso_pagos';
    
    /**
     * @OA\Get(
     *     path="/cursos",
     *     tags={"Cursos"},
     *     summary="Listar cursos y pedidos",
     *     description="Obtiene la lista de pedidos de cursos con filtros y paginación",
     *     operationId="getCursos",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="campanas", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="fechaInicio", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="fechaFin", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="estados_pago", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="tipos_curso", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sobrepagado", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Cursos obtenidos exitosamente"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            $search = $request->get('search', '');
            $campanas    = $request->get('campanas', '');
            $fechaInicio = $request->get('fechaInicio', '');
            $fechaFin = $request->get('fechaFin', '');
            $estadoPago = $request->get('estados_pago', '');
            $tipoCurso = $request->get('tipos_curso', '');
            $sobrepagado = $request->get('sobrepagado', '');

            // Obtener usuario autenticado
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Construir la consulta principal con todos los joins necesarios
            $query = DB::table('pedido_curso AS PC')
                ->select([
                    'PC.*',
                    'CLI.Fe_Nacimiento',
                    'CLI.Nu_Como_Entero_Empresa',
                    'CLI.No_Otros_Como_Entero_Empresa',
                    'DIST.No_Distrito',
                    'PROV.No_Provincia',
                    'DEP.No_Departamento',
                    'TDI.No_Tipo_Documento_Identidad_Breve',
                    'P.No_Pais',
                    'CLI.Nu_Tipo_Sexo',
                    'CLI.No_Entidad',
                    'CLI.Nu_Documento_Identidad',
                    'CLI.Nu_Celular_Entidad',
                    'CLI.Txt_Email_Entidad',
                    'CLI.Nu_Edad',
                    'M.No_Signo',
                    'USR.ID_Usuario',
                    'USR.No_Usuario',
                    'USR.No_Password',
                    'CAMP.Fe_Fin',
                    'CAMP.Fe_Inicio',
                    DB::raw('tipo_curso'), // Por defecto virtual, se puede modificar según la lógica de negocio
                    DB::raw('(
                        SELECT COUNT(*)
                        FROM pedido_curso_pagos AS cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                        AND ccp.name = "ADELANTO"
                    ) AS pagos_count'),
                    DB::raw('(
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM pedido_curso_pagos AS cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                        AND ccp.name = "ADELANTO"
                    ) AS total_pagos')
                ])
                ->leftJoin('pais AS P', 'P.ID_Pais', '=', 'PC.ID_Pais')
                ->leftJoin('entidad AS CLI', 'CLI.ID_Entidad', '=', 'PC.ID_Entidad')
                ->leftJoin('tipo_documento_identidad AS TDI', 'TDI.ID_Tipo_Documento_Identidad', '=', 'CLI.ID_Tipo_Documento_Identidad')
                ->leftJoin('moneda AS M', 'M.ID_Moneda', '=', 'PC.ID_Moneda')
                ->leftJoin('usuario AS USR', 'USR.ID_Entidad', '=', 'CLI.ID_Entidad')
                ->leftJoin('campana_curso AS CAMP', 'CAMP.ID_Campana', '=', 'PC.ID_Campana')
                ->leftJoin('distrito AS DIST', 'DIST.ID_Distrito', '=', 'CLI.ID_Distrito')
                ->leftJoin('provincia AS PROV', 'PROV.ID_Provincia', '=', 'CLI.ID_Provincia')
                ->leftJoin('departamento AS DEP', 'DEP.ID_Departamento', '=', 'CLI.ID_Departamento')
                ->where('PC.ID_Empresa', $user->ID_Empresa);

            // Aplicar filtros
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('CLI.No_Entidad', 'like', "%$search%")
                        ->orWhere('CLI.Nu_Documento_Identidad', 'like', "%$search%")
                        ->orWhere('CLI.Nu_Celular_Entidad', 'like', "%$search%")
                        ->orWhere('PC.ID_Pedido_Curso', 'like', "%$search%")
                        // Agrega aquí más campos si quieres que el buscador sea más amplio
                    ;
                });
            }


            if ($fechaInicio && $fechaFin) {
                $query->whereBetween('PC.Fe_Registro', [$fechaInicio, $fechaFin]);
            }

            if ($campanas && $campanas != '0') {
                $query->where('PC.ID_Campana', $campanas);
            }

            // Aplicar filtro de estado de pago
            if ($estadoPago && $estadoPago != '0') {
                $query->whereRaw('(
                    CASE 
                        WHEN (
                            SELECT IFNULL(SUM(cccp.monto), 0)
                            FROM pedido_curso_pagos AS cccp
                            JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                            WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                            AND ccp.name = "ADELANTO"
                        ) = 0 THEN "pendiente"
                        WHEN (
                            SELECT IFNULL(SUM(cccp.monto), 0)
                            FROM pedido_curso_pagos AS cccp
                            JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                            WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                            AND ccp.name = "ADELANTO"
                        ) < PC.Ss_Total AND (
                            SELECT IFNULL(SUM(cccp.monto), 0)
                            FROM pedido_curso_pagos AS cccp
                            JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                            WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                            AND ccp.name = "ADELANTO"
                        ) > 0 THEN "adelanto"
                        WHEN (
                            SELECT IFNULL(SUM(cccp.monto), 0)
                            FROM pedido_curso_pagos AS cccp
                            JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                            WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                            AND ccp.name = "ADELANTO"
                        ) = PC.Ss_Total THEN "pagado"
                        WHEN (
                            SELECT IFNULL(SUM(cccp.monto), 0)
                            FROM pedido_curso_pagos AS cccp
                            JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                            WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                            AND ccp.name = "ADELANTO"
                        ) > PC.Ss_Total THEN "sobrepagado"
                        ELSE "pendiente"
                    END
                ) = ?', [$estadoPago]);
            }
            // Filtro por tipo de curso
            if ($tipoCurso && $tipoCurso !== '') {
                $query->where('PC.tipo_curso', $tipoCurso);
            }

            

            // Ordenar por id descendente
            $query->orderBy('PC.ID_Pedido_Curso', 'desc');

            // Obtener el total antes de paginar para el filtro de estado
            $totalQuery = clone $query;
            $totalRecords = $totalQuery->count();

            // Aplicar paginación
            $offset = ($page - 1) * $perPage;
            $cursos = $query->offset($offset)->limit($perPage)->get();

            // Procesar los datos para agregar información adicional
            $cursosProcessed = $cursos->map(function ($curso) {
                // Determinar el estado del pago
                $estado = 'pendiente';
                if ($curso->total_pagos == 0) {
                    $estado = 'pendiente';
                } elseif ($curso->total_pagos < $curso->Ss_Total && $curso->total_pagos > 0) {
                    $estado = 'adelanto';
                } elseif ($curso->total_pagos == $curso->Ss_Total) {
                    $estado = 'pagado';
                } elseif ($curso->total_pagos > $curso->Ss_Total) {
                    $estado = 'sobrepagado';
                }

                // Verificar si corresponde constancia (curso en vivo y fecha fin pasada)
                $fechaHoy = now()->toDateString();
                $fechaFin = $curso->Fe_Fin ?? null;
                $tipoCurso = $curso->tipo_curso ?? null;

                if ($tipoCurso == 1 && $fechaFin && strtotime($fechaHoy) > strtotime($fechaFin)) {
                    $estado = 'constancia';
                }
                return [
                    'ID_Pedido_Curso' => $curso->ID_Pedido_Curso,
                    'Fe_Registro' => DateHelper::formatDate($curso->Fe_Registro, '-', 0),
                    'Fe_Registro_Original' => $curso->Fe_Registro,
                    'No_Entidad' => $curso->No_Entidad,
                    'No_Tipo_Documento_Identidad_Breve' => $curso->No_Tipo_Documento_Identidad_Breve,
                    'Nu_Documento_Identidad' => $curso->Nu_Documento_Identidad,
                    'Nu_Celular_Entidad' => $curso->Nu_Celular_Entidad,
                    'Txt_Email_Entidad' => $curso->Txt_Email_Entidad,
                    'tipo_curso' => strval($curso->tipo_curso),
                    'ID_Campana' => $curso->ID_Campana,
                    'ID_Usuario' => $curso->ID_Usuario,
                    'Nu_Estado_Usuario_Externo' => $curso->Nu_Estado_Usuario_Externo ?? 1,
                    'Ss_Total' => $curso->Ss_Total,
                    'Nu_Estado' => $curso->Nu_Estado,
                    'Fe_Fin' => DateHelper::formatDate($curso->Fe_Fin, '-', 0),
                    'Fe_Fin_Original' => $curso->Fe_Fin,
                    'pagos_count' => $curso->pagos_count,
                    'total_pagos' => $curso->total_pagos,
                    'estado_pago' => $estado,
                    'puede_constancia' => $curso->send_constancia=='SENDED'?true:false,
                    // Información adicional del cliente
                    'Fe_Nacimiento' => DateHelper::formatDate($curso->Fe_Nacimiento, '-', 0),
                    'Fe_Nacimiento_Original' => $curso->Fe_Nacimiento,
                    'Nu_Como_Entero_Empresa' => $curso->Nu_Como_Entero_Empresa,
                    'No_Otros_Como_Entero_Empresa' => $curso->No_Otros_Como_Entero_Empresa,
                    'No_Distrito' => $curso->No_Distrito,
                    'No_Provincia' => $curso->No_Provincia,
                    'No_Departamento' => $curso->No_Departamento,
                    'No_Pais' => $curso->No_Pais,
                    'Nu_Tipo_Sexo' => $curso->Nu_Tipo_Sexo,
                    'Nu_Edad' => $curso->Nu_Edad,
                    'No_Signo' => $curso->No_Signo,
                    'No_Usuario' => $curso->No_Usuario
                ];
            });
            $totalAmount = $cursosProcessed->sum('total_pagos');

            // Obtener campañas activas para el frontend
            $campanas = Campana::activas()->get(['ID_Campana', 'Fe_Inicio', 'Fe_Fin']);

            return response()->json([
                'success' => true,
                'data' => $cursosProcessed,
                'pagination' => [
                    'current_page' => (int)$page,
                    'last_page' => ceil($totalRecords / $perPage),
                    'per_page' => (int)$perPage,
                    'total' => $totalRecords,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $totalRecords),
                ],
                'headers' => [
                    'importe_total' => [
                        'value' => $totalAmount,
                        'label' => 'Importe Total'
                    ],
                    'total_pedidos' => [
                        'value' => $totalRecords,
                        'label' => 'Total Pedidos'
                    ]
                ],
                'filters' => [
                    'campanas' => $campanas
                ],
                'importe_total' => $totalAmount,
                'total_pedidos' => $totalRecords
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los cursos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/cursos/filter-options",
     *     tags={"Cursos"},
     *     summary="Obtener opciones de filtro para cursos",
     *     description="Obtiene las opciones disponibles para filtrar cursos (campañas, estados, tipos)",
     *     operationId="filterOptions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Opciones obtenidas exitosamente")
     * )
     *
     * Obtener opciones de filtro para cursos
     */
    public function filterOptions()
    {
        try {
            // Obtener campañas activas
            $campanas = Campana::activas()
                ->select('ID_Campana', 'Fe_Inicio', 'Fe_Fin')
                ->orderBy('Fe_Inicio', 'desc')
                ->get()
                ->map(function ($campana) {
                    $mes = Carbon::parse($campana->Fe_Inicio)->locale('es')->monthName;
                    return [
                        'value' => $campana->ID_Campana,
                        'label' => $mes,

                    ];
                });

            //campanas get month in spanish from Fe_Inicio as label and id as value

            $estadosPago = [
                ['value' => '0', 'label' => 'Todos'],
                ['value' => 'pendiente', 'label' => 'Pendiente'],
                ['value' => 'adelanto', 'label' => 'Adelanto'],
                ['value' => 'pagado', 'label' => 'Pagado'],
                ['value' => 'sobrepagado', 'label' => 'Sobrepagado'],
                ['value' => 'constancia', 'label' => 'Constancia']
            ];

            // Tipos de curso
            $tiposCurso = [
                ['value' => 0, 'label' => 'Virtual'],
                ['value' => 1, 'label' => 'En vivo']
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    [
                        'key' => 'fechaInicio',
                        'label' => 'Fecha inicio',
                        'placeholder' => 'Desde',
                        'type' => 'date',
                        'options' => []
                    ],
                    [
                        'key' => 'fechaFin',
                        'label' => 'Fecha fin',
                        'placeholder' => 'Hasta',
                        'type' => 'date',
                        'options' => []
                    ],
                    [
                        'key' => 'campanas',
                        'label' => 'Campañas',
                        'placeholder' => 'Selecciona una campaña',
                        'options' => $campanas
                    ],
                    [
                        'key' => 'estados_pago',
                        'label' => 'Estados de pago',
                        'placeholder' => 'Selecciona un estado de pago',
                        'options' => $estadosPago
                    ],
                    [
                        'key' => 'tipos_curso',
                        'label' => 'Tipos de curso',
                        'placeholder' => 'Selecciona un tipo de curso',
                        'options' => $tiposCurso
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener opciones de filtro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/cursos/{idPedido}",
     *     tags={"Cursos"},
     *     summary="Eliminar pedido de curso",
     *     description="Elimina un pedido de curso por su ID",
     *     operationId="eliminarPedido",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="idPedido",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Pedido eliminado exitosamente"),
     *     @OA\Response(response=404, description="Pedido no encontrado")
     * )
     *
     * Eliminar pedido (migrado de CodeIgniter)
     */
    public function eliminarPedido($idPedido)
    {
        try {
            // Deshabilitar verificación de claves foráneas
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            
            $deleted = DB::table('pedido_curso')
                ->where('ID_Pedido_Curso', $idPedido)
                ->delete();
            
            // Rehabilitar verificación de claves foráneas
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            
            if ($deleted > 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pedido eliminado correctamente'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el pedido o no se pudo eliminar'
                ]);
            }
        } catch (\Exception $e) {
            // Asegurar que se rehabilite la verificación de claves foráneas en caso de error
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            
            Log::error('Error en eliminarPedido: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el pedido: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Actualizar datos del cliente
     */
  
    /**
     * @OA\Get(
     *     path="/cursos/{idPedido}/cliente",
     *     tags={"Cursos"},
     *     summary="Obtener datos del cliente por pedido",
     *     description="Obtiene los datos del cliente asociado a un pedido de curso",
     *     operationId="getDatosClientePorPedido",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="idPedido",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Datos del cliente obtenidos exitosamente"),
     *     @OA\Response(response=404, description="Pedido no encontrado")
     * )
     *
     * Obtener datos del cliente por pedido
     */
    public function getDatosClientePorPedido($idPedido)
    {
        $data = DB::table('pedido_curso AS PC')
            ->join('entidad AS CLI', 'CLI.ID_Entidad', '=', 'PC.ID_Entidad')
            ->join('pais AS P', 'P.ID_Pais', '=', 'PC.ID_Pais')
            ->leftJoin('usuario AS USR', 'USR.ID_Entidad', '=', 'CLI.ID_Entidad')
            ->leftJoin('distrito AS DI', 'DI.ID_Distrito', '=', 'CLI.ID_Distrito')
            ->leftJoin('provincia AS PR', 'PR.ID_Provincia', '=', 'CLI.ID_Provincia')
            ->leftJoin('departamento AS D', 'D.ID_Departamento', '=', 'CLI.ID_Departamento')
            ->leftJoin('campana_curso AS CC', 'CC.ID_Campana', '=', 'PC.ID_Campana')
            ->where('PC.ID_Pedido_Curso', $idPedido)
            ->selectRaw("
                CLI.ID_Entidad as id_entidad,
                CLI.No_Entidad as nombres,
                CLI.Nu_Tipo_Sexo as sexo,
                CLI.Nu_Documento_Identidad as dni,
                CLI.Nu_Celular_Entidad as whatsapp,
                CLI.Txt_Email_Entidad as correo,
                CLI.Fe_Nacimiento as nacimiento,
                CLI.Nu_Como_Entero_Empresa as red_social,
                P.ID_Pais as id_pais,
                D.ID_Departamento as id_departamento,
                PR.ID_Provincia as id_provincia,
                DI.ID_Distrito as id_distrito,
                P.No_Pais as pais,
                D.No_Departamento as departamento,
                PR.No_Provincia as provincia,
                DI.No_Distrito as distrito,
                USR.ID_Usuario as id_usuario, 
                IFNULL(USR.usuario_moodle,USR.No_Usuario) as usuario_moodle,
                USR.No_Password as password_moodle,
                PC.ID_Campana,
                PC.tipo_curso as tipo_curso,
                PC.Nu_Estado as Nu_Estado, 
                PC.Nu_Estado_Usuario_Externo as Nu_Estado_Usuario_Externo,
                PC.ID_Pedido_Curso as id_pedido_curso,
                PC.url_constancia as url_constancia,
                MONTH(CC.Fe_Inicio) as mes_numero,
                PC.from_intranet as from_intranet
            ")
            ->first();
            //get url constancia and use filetrait generate file
        $meses_es = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];
        $data = (array)$data;
        if($data['from_intranet'] == 1){
            $data['url_constancia'] = $this->generateImageUrl($data['url_constancia']);
        }else{
            $data['url_constancia'] = $this->generateImageUrlRedisProyect($data['url_constancia']);
        }

        $data['mes_nombre'] = isset($data['mes_numero']) ? ($meses_es[(int)$data['mes_numero']] ?? '') : '';
        $data['password_moodle'] = $this->ciDecrypt($data['password_moodle']);
        return response()->json(['status' => 'success', 'data' => $data]);
    }



    /**
     * @OA\Put(
     *     path="/cursos/usuario-moodle/{idUsuario}",
     *     tags={"Cursos"},
     *     summary="Actualizar usuario Moodle",
     *     description="Actualiza los datos del usuario en Moodle",
     *     operationId="setUsuarioMoodle",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idUsuario", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="usuario_moodle", type="string"),
     *             @OA\Property(property="No_Password", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Usuario actualizado exitosamente")
     * )
     *
     * Actualizar usuario Moodle
     */
    public function setUsuarioMoodle(Request $request, $idUsuario)
    {
        $data = $request->only(['usuario_moodle', 'No_Password']);
        $updated = DB::table('usuario')->where('ID_Usuario', $idUsuario)->update($data);
        if ($updated) {
            return response()->json(['status' => 'success', 'message' => 'Usuario actualizado correctamente']);
        }
        return response()->json(['status' => 'error', 'message' => 'Error al actualizar el usuario']);
    }

    /**
     * @OA\Put(
     *     path="/cursos/{idPedido}",
     *     tags={"Cursos"},
     *     summary="Actualizar pedido de curso",
     *     description="Actualiza los datos de un pedido de curso",
     *     operationId="actualizarPedidoPublic",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idPedido", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object")),
     *     @OA\Response(response=200, description="Pedido actualizado exitosamente")
     * )
     *
     * Actualizar pedido
     */
    public function actualizarPedidoPublic(Request $request, $idPedido)
    {
        $data = $request->all();
        $updated = DB::table('pedido_curso')->where('ID_Pedido_Curso', $idPedido)->update($data);
        if ($updated) {
            return response()->json(['status' => 'success', 'message' => 'Registro modificado']);
        }
        return response()->json(['status' => 'error', 'message' => 'Error al modificar']);
    }

    /**
     * @OA\Put(
     *     path="/cursos/{idPedido}/importe",
     *     tags={"Cursos"},
     *     summary="Actualizar importe de pedido",
     *     description="Actualiza el importe total de un pedido de curso",
     *     operationId="actualizarImportePedido",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idPedido", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="Ss_Total", type="number")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Importe actualizado exitosamente")
     * )
     */
    public function actualizarImportePedido(Request $request, $idPedido)
    {
        $importe = $request->input('importe');
        $updated = DB::table('pedido_curso')->where('ID_Pedido_Curso', $idPedido)->update(['Ss_Total' => $importe]);
        if ($updated) {
            return response()->json(['status' => 'success', 'message' => 'Importe actualizado correctamente']);
        }
        return response()->json(['status' => 'warning', 'message' => 'No se modificó ningún dato']);
    }


    /**
     * @OA\Delete(
     *     path="/cursos/pago/{idPagoCurso}",
     *     tags={"Cursos"},
     *     summary="Eliminar pago de curso",
     *     description="Elimina un pago de curso y su voucher asociado",
     *     operationId="borrarPagoCurso",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idPagoCurso", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Pago eliminado exitosamente"),
     *     @OA\Response(response=404, description="Pago no encontrado")
     * )
     *
     * Eliminar pago de curso y borrar voucher (migrado de CodeIgniter)
     */
    public function borrarPagoCurso($idPagoCurso)
    {
        try {
            // Buscar el pago por ID
            $pago = DB::table($this->table_pedido_curso_pagos)
                ->select('voucher_url')
                ->where('id', $idPagoCurso)
                ->first();

            if (!$pago) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pago no encontrado'
                ]);
            }

            // Eliminar el archivo voucher si existe
            if (!empty($pago->voucher_url)) {
                $path = storage_path('app/' . $pago->voucher_url);
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            // Eliminar el registro del pago
            $deleted = DB::table($this->table_pedido_curso_pagos)
                ->where('id', $idPagoCurso)
                ->delete();

            if ($deleted > 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pago eliminado correctamente'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo eliminar el pago'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error en borrarPagoCurso: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el pago: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/cursos/pagos",
     *     tags={"Cursos"},
     *     summary="Guardar pago de cliente",
     *     description="Guarda un pago de cliente con voucher adjunto",
     *     operationId="saveClientePagosCurso",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"voucher", "idPedido", "monto", "fecha", "banco"},
     *                 @OA\Property(property="voucher", type="string", format="binary"),
     *                 @OA\Property(property="idPedido", type="integer"),
     *                 @OA\Property(property="monto", type="number"),
     *                 @OA\Property(property="fecha", type="string", format="date"),
     *                 @OA\Property(property="banco", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Pago guardado exitosamente"),
     *     @OA\Response(response=422, description="Validación fallida")
     * )
     *
     * Guardar pago de cliente (con voucher)
     */
    public function saveClientePagosCurso(Request $request)
    {
        try {
            $request->validate([
                'voucher' => 'required|file',
                'idPedido' => 'required|integer',
                'monto' => 'required|numeric',
                'fecha' => 'required|date',
                'banco' => 'required|string'
            ]);
            $voucher = $request->file('voucher');
            $voucherUrl = $voucher->store('public/vouchers');
            $data = [
                'voucher_url' => $voucherUrl,
                'id_pedido_curso' => $request->idPedido,
                'id_concept' => 1, // ADELANTO
                'monto' => $request->monto,
                'payment_date' => $request->fecha,
                'banco' => $request->banco
            ];
            
            // Insertar el pago en la base de datos
            $inserted = DB::table('pedido_curso_pagos')->insert($data);
            
            if ($inserted) {
                // Obtener información del cliente del curso para la notificación
                $cursoInfo = DB::table('pedido_curso AS PC')
                    ->join('entidad AS CLI', 'CLI.ID_Entidad', '=', 'PC.ID_Entidad')
                    ->select('CLI.No_Entidad as cliente_nombre', 'CLI.Nu_Documento_Identidad as cliente_documento', 'PC.ID_Pedido_Curso as pedido_id')
                    ->where('PC.ID_Pedido_Curso', $request->idPedido)
                    ->first();

                // Obtener usuario autenticado
                $user = JWTAuth::parseToken()->authenticate();

                // Crear notificación para el perfil Administración
                if ($cursoInfo && $user) {
                    Notificacion::create([
                        'titulo' => 'Nuevo Pago de Curso Registrado',
                        'mensaje' => "Se ha registrado un pago de curso de S/ {$request->monto} para el cliente {$cursoInfo->cliente_nombre}",
                        'descripcion' => "Cliente: {$cursoInfo->cliente_nombre} | Documento: {$cursoInfo->cliente_documento} | Monto: S/ {$request->monto} | Banco: {$request->banco} | Fecha: {$request->fecha}",
                        'modulo' => Notificacion::MODULO_CURSOS,
                        'rol_destinatario' => Usuario::ROL_ADMINISTRACION,
                        'navigate_to' => 'verificacion',
                        'navigate_params' => [
                            'idPedido' => $request->idPedido,
                            'tab' => 'cursos'
                        ],
                        'tipo' => Notificacion::TIPO_SUCCESS,
                        'icono' => 'mdi:cash-check',
                        'prioridad' => Notificacion::PRIORIDAD_ALTA,
                        'referencia_tipo' => 'pago_curso',
                        'referencia_id' => $request->idPedido,
                        'activa' => true,
                        'creado_por' => $user->ID_Usuario,
                        'configuracion_roles' => [
                            Usuario::ROL_ADMINISTRACION => [
                                'titulo' => 'Pago Curso - Verificar',
                                'mensaje' => "Nuevo pago de S/ {$request->monto} para verificar",
                                'descripcion' => "Cliente: {$cursoInfo->cliente_nombre} | Pedido: {$cursoInfo->pedido_id}"
                            ]
                        ]
                    ]);
                }
                
                return response()->json(['success' => true, 'message' => 'Pago guardado exitosamente', 'data' => $data]);
            } else {
                return response()->json(['success' => false, 'message' => 'Error al guardar el pago en la base de datos']);
            }
        } catch (\Exception $e) {
            Log::error('Error en saveClientePagosCurso: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al guardar el pago: ' . $e->getMessage()]);
        }
    }

    /**
     * @OA\Get(
     *     path="/cursos/{idPedidoCurso}/pagos",
     *     tags={"Cursos"},
     *     summary="Obtener pagos de un pedido de curso",
     *     description="Obtiene todos los pagos detallados de un pedido de curso",
     *     operationId="getPagosCursoPedido",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idPedidoCurso", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Pagos obtenidos exitosamente")
     * )
     *
     * Obtener pagos detallados de un pedido
     */
    public function getPagosCursoPedido($idPedidoCurso)
    {
        try {
            $pagos = DB::table('pedido_curso_pagos')
                ->join('pedido_curso_pagos_concept', 'pedido_curso_pagos.id_concept', '=', 'pedido_curso_pagos_concept.id')
                ->where('id_pedido_curso', $idPedidoCurso)
                ->orderBy('payment_date', 'DESC')
                ->select('pedido_curso_pagos.*', 'pedido_curso_pagos_concept.name as concepto')
                ->get();
            return response()->json(['status' => 'success', 'data' => $pagos]);
        } catch (\Exception $e) {
            Log::error('Error en getPagosCursoPedido: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Error al obtener los pagos del curso: ' . $e->getMessage()]);
        }
    }

    /**
     * @OA\Post(
     *     path="/cursos/campanas",
     *     tags={"Cursos - Campañas"},
     *     summary="Crear campaña de curso",
     *     description="Crea una nueva campaña de curso con fechas y días",
     *     operationId="crearCampana",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="fe_inicio", type="string", format="date"),
     *             @OA\Property(property="fe_fin", type="string", format="date"),
     *             @OA\Property(property="dias", type="array", @OA\Items(type="string", format="date"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Campaña creada exitosamente")
     * )
     *
     * Métodos de campañas: crear, editar, borrar, obtener
     */
    public function crearCampana(Request $request)
    {
        $data = [
            'Fe_Inicio' => $request->fe_inicio,
            'Fe_Fin' => $request->fe_fin,
            'Fe_Creacion' => now()
        ];
        DB::table('campana_curso')->insert($data);
        $id = DB::getPdo()->lastInsertId();
        // Insertar días
        DB::table('campana_curso_dias')->where('id_campana', $id)->delete();
        foreach ($request->dias as $dia) {
            DB::table('campana_curso_dias')->insert(['id_campana' => $id, 'fecha' => $dia]);
        }
        return response()->json(['status' => 'success', 'message' => 'Campaña registrada correctamente', 'id' => $id]);
    }

    /**
     * @OA\Put(
     *     path="/cursos/campanas/{id}",
     *     tags={"Cursos - Campañas"},
     *     summary="Editar campaña de curso",
     *     description="Actualiza una campaña de curso existente",
     *     operationId="editarCampana",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="fe_inicio", type="string", format="date"),
     *             @OA\Property(property="fe_fin", type="string", format="date"),
     *             @OA\Property(property="dias", type="array", @OA\Items(type="string", format="date"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Campaña actualizada exitosamente")
     * )
     */
    public function editarCampana(Request $request, $id)
    {
        $data = [
            'Fe_Inicio' => $request->fe_inicio,
            'Fe_Fin' => $request->fe_fin
        ];
        DB::table('campana_curso')->where('ID_Campana', $id)->update($data);
        DB::table('campana_curso_dias')->where('id_campana', $id)->delete();
        foreach ($request->dias as $dia) {
            DB::table('campana_curso_dias')->insert(['id_campana' => $id, 'fecha' => $dia]);
        }
        return response()->json(['status' => 'success', 'message' => 'Campaña actualizada correctamente']);
    }

    /**
     * @OA\Delete(
     *     path="/cursos/campanas/{id}",
     *     tags={"Cursos - Campañas"},
     *     summary="Eliminar campaña de curso",
     *     description="Elimina (soft delete) una campaña de curso",
     *     operationId="borrarCampana",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Campaña eliminada exitosamente")
     * )
     */
    public function borrarCampana($id)
    {
        DB::table('campana_curso')->where('ID_Campana', $id)->update(['Fe_Borrado' => now()]);
        return response()->json(['status' => 'success', 'message' => 'Campaña eliminada correctamente']);
    }

    /**
     * @OA\Get(
     *     path="/cursos/campanas",
     *     tags={"Cursos - Campañas"},
     *     summary="Obtener campañas de cursos",
     *     description="Lista todas las campañas de cursos activas",
     *     operationId="getCampanas",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Campañas obtenidas exitosamente")
     * )
     */
    public function getCampanas()
    {
        $campanas = DB::table('campana_curso')
            ->select('ID_Campana', 'Fe_Creacion', 'Fe_Inicio', 'Fe_Fin', DB::raw('MONTH(Fe_Inicio) as Mes_Numero'))
            ->whereNull('Fe_Borrado')
            ->get();
        $meses_es = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];
        foreach ($campanas as &$row) {
            $row->No_Campana = $meses_es[(int)$row->Mes_Numero] ?? '';
        }
        return response()->json(['status' => 'success', 'data' => $campanas]);
    }

    /**
     * @OA\Get(
     *     path="/cursos/campanas/{id}",
     *     tags={"Cursos - Campañas"},
     *     summary="Obtener campaña por ID",
     *     description="Obtiene una campaña específica con sus días",
     *     operationId="getCampanaById",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Campaña obtenida exitosamente")
     * )
     */
    public function getCampanaById($id)
    {
        $campana = DB::table('campana_curso as c')
            ->select('c.ID_Campana', 'c.Fe_Creacion', 'c.Fe_Inicio', 'c.Fe_Fin', DB::raw('MONTH(c.Fe_Inicio) as Mes_Numero'))
            ->where('c.ID_Campana', $id)
            ->first();
        $dias = DB::table('campana_curso_dias')->where('id_campana', $id)->get();
        $campana->dias = $dias;
        return response()->json(['status' => 'success', 'data' => $campana]);
    }

    /**
     * @OA\Post(
     *     path="/cursos/tipo-curso",
     *     tags={"Cursos"},
     *     summary="Asignar tipo de curso",
     *     description="Asigna un tipo de curso a un pedido",
     *     operationId="asignarTipoCurso",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id_pedido", type="integer"),
     *             @OA\Property(property="id_tipo_curso", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Tipo de curso asignado exitosamente")
     * )
     *
     * Asignar tipo de curso (migrado de CodeIgniter)
     */
    public function asignarTipoCurso(Request $request)
    {
        try {
            $id_pedido = $request->input('id_pedido');
            $id_tipo_curso = $request->input('id_tipo_curso');
            Log::info('id_pedido: ' . $id_pedido);
            Log::info('id_tipo_curso: ' . $id_tipo_curso);

            if ($id_pedido !== null && $id_tipo_curso !== null) {
                $updated = DB::table('pedido_curso')
                    ->where('ID_Pedido_Curso', $id_pedido)
                    ->update(['tipo_curso' => $id_tipo_curso]);

                if ($updated > 0) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Tipo de curso actualizado'
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se encontró el pedido o no se realizaron cambios'
                    ]);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos incompletos'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error en asignarTipoCurso: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el tipo de curso: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/cursos/asignar-campana",
     *     tags={"Cursos"},
     *     summary="Asignar campaña a pedido de curso",
     *     description="Asigna una campaña a un pedido de curso",
     *     operationId="asignarCampanaPedidoCurso",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id_pedido", type="integer"),
     *             @OA\Property(property="estado_pedido", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Campaña asignada exitosamente")
     * )
     *
     * Asignar campaña a pedido (migrado de CodeIgniter)
     */
    public function asignarCampanaPedidoCurso(Request $request)
    {
        try {
            $updated = DB::table('pedido_curso')
                ->where('ID_Pedido_Curso', $request->id_pedido)
                ->update(['ID_Campana' => $request->estado_pedido]);

            if ($updated > 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Campaña asignada correctamente'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se modificó ningún dato'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error en asignarCampanaPedido: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar la campaña: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * @OA\Get(
     *     path="/cursos/pagos-general",
     *     tags={"Cursos"},
     *     summary="Obtener pagos de cursos general",
     *     description="Obtiene la lista general de pagos de cursos con filtros",
     *     operationId="getPagosCurso",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Pagos obtenidos exitosamente"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     *
     * Obtener pagos de cursos (migrado de CodeIgniter)
     */
    public function getPagosCurso(Request $request)
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

            $query = DB::table('pedido_curso AS CC')
                ->select([
                    'CC.*',
                    'CLI.Fe_Nacimiento',
                    'CLI.Nu_Como_Entero_Empresa',
                    'CLI.No_Otros_Como_Entero_Empresa',
                    'DIST.No_Distrito',
                    'PROV.No_Provincia',
                    'DEP.No_Departamento',
                    'TDI.No_Tipo_Documento_Identidad_Breve',
                    'P.No_Pais',
                    'CLI.Nu_Tipo_Sexo',
                    'CLI.No_Entidad',
                    'CLI.Nu_Documento_Identidad',
                    'CLI.Nu_Celular_Entidad',
                    'CLI.Txt_Email_Entidad',
                    'CLI.Nu_Edad',
                    'M.No_Signo',
                    'USR.ID_Usuario',
                    'USR.No_Usuario',
                    'USR.No_Password',
                    DB::raw('(
                        SELECT COUNT(*)
                        FROM pedido_curso_pagos as cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = CC.ID_Pedido_Curso
                        AND (ccp.name = "ADELANTO")
                    ) AS pagos_count'),
                    DB::raw('(
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM pedido_curso_pagos as cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = CC.ID_Pedido_Curso
                        AND (ccp.name = "ADELANTO")
                    ) AS total_pagos'),
                    DB::raw('(SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            "id_pago", ccp2.id,
                            "monto", ccp2.monto,
                            "concepto", ccpc2.name,
                            "status", ccp2.status,
                            "payment_date", ccp2.payment_date,
                            "voucher_url", ccp2.voucher_url,
                            "banco", ccp2.banco
                        )
                    ) FROM pedido_curso_pagos as ccp2
                    LEFT JOIN pedido_curso_pagos_concept as ccpc2 ON ccp2.id_concept = ccpc2.id
                    WHERE ccp2.id_pedido_curso = CC.ID_Pedido_Curso
                    AND ccp2.id_concept = 1
                    ) as pagos_details')
                ])
                ->join('pais AS P', 'P.ID_Pais', '=', 'CC.ID_Pais')
                ->join('entidad AS CLI', 'CLI.ID_Entidad', '=', 'CC.ID_Entidad')
                ->join('tipo_documento_identidad AS TDI', 'TDI.ID_Tipo_Documento_Identidad', '=', 'CLI.ID_Tipo_Documento_Identidad')
                ->join('moneda AS M', 'M.ID_Moneda', '=', 'CC.ID_Moneda')
                ->join('usuario AS USR', 'USR.ID_Entidad', '=', 'CLI.ID_Entidad')
                ->leftJoin('distrito AS DIST', 'DIST.ID_Distrito', '=', 'CLI.ID_Distrito')
                ->leftJoin('provincia AS PROV', 'PROV.ID_Provincia', '=', 'CLI.ID_Provincia')
                ->leftJoin('departamento AS DEP', 'DEP.ID_Departamento', '=', 'CLI.ID_Departamento')
                ->where('CC.ID_Empresa', $user->ID_Empresa);

            // Filtros de fecha
            if ($request->filled('Filtro_Fe_Inicio')) {
                $query->whereRaw('DATE(CC.Fe_Registro) >= ?', [$request->Filtro_Fe_Inicio]);
            }

            if ($request->filled('Filtro_Fe_Fin')) {
                $query->whereRaw('DATE(CC.Fe_Registro) <= ?', [$request->Filtro_Fe_Fin]);
            }
            if($request->filled('search')) {
                $query->where('CLI.No_Entidad', 'like', "%$request->search%")
                    ->orWhere('CLI.Nu_Documento_Identidad', 'like', "%$request->search%")
                    ->orWhere('CC.ID_Pedido_Curso', 'like', "%$request->search%");
            }
            // Ordenar por ID descendente por defecto
            $query->orderBy('CC.ID_Pedido_Curso', 'desc');

            // Aplicar paginación
            $perPage = $request->get('limit', 100);
            $page = $request->get('page', 1);
            $result = $query->paginate($perPage, ['*'], 'page', $page);

            // Procesar los datos después de la paginación
            $processedData = collect($result->items())->map(function ($item) {
                $item->pagos_details = json_decode($item->pagos_details, true) ?? [];
                $item->total_amount = collect($item->pagos_details)->sum('monto');
                return $item;
            });
         

            // Calcular el total amount de todos los registros
            $totalAmount = $processedData->sum('total_amount');

            return response()->json([
                'success' => true,
                'data' => $processedData->values()->all(),
                'pagination' => [
                    'total' => $result->total(),
                    'per_page' => $result->perPage(),
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                    'from' => $result->firstItem(),
                    'to' => $result->lastItem()
                ],
                'total_amount' => $totalAmount,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en getPagosCurso: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pagos de cursos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/cursos/change-importe",
     *     tags={"Cursos"},
     *     summary="Cambiar importe de pedido",
     *     description="Cambia el importe total de un pedido de curso",
     *     operationId="changeImportePedido",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id_pedido", type="integer"),
     *             @OA\Property(property="importe", type="number")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Importe actualizado exitosamente")
     * )
     */
    public function changeImportePedido(Request $request)
    {
        try {
            $importe = $request->importe;
            $idPedido = $request->id_pedido;
            DB::table('pedido_curso')->where('ID_Pedido_Curso', $idPedido)->update(['Ss_Total' => $importe]);
            return response()->json(['success' => true, 'message' => 'Importe actualizado correctamente']);
        } catch (\Exception $e) {
            Log::error('Error en changeImportePedido: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el importe del pedido',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/cursos/crear-usuario-moodle",
     *     tags={"Cursos"},
     *     summary="Crear usuario en Moodle",
     *     description="Crea un usuario en la plataforma Moodle para cursos",
     *     operationId="crearUsuarioCursosMoodle",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id_usuario", type="integer"),
     *             @OA\Property(property="id_pedido", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Usuario Moodle creado exitosamente")
     * )
     */
    public function crearUsuarioCursosMoodle(Request $request)
    {
        try {
            $id = $request->input('id_usuario');
            $id_pedido_curso = $request->input('id_pedido');
            
            // Buscar usuario y obtener datos completos incluyendo teléfono
            $response_usuario_bd = $this->getUsuario($id);
            if ($response_usuario_bd['status'] == 'success') {
                $result = $response_usuario_bd['result'][0];
                Log::error('result: ' . print_r($result, true));

                // Obtener datos adicionales del usuario (teléfono)
                ///get ID_Entidad from pedido_curso where ID_Pedido_Curso = $id_pedido_curso
                $ID_Entidad = DB::table('pedido_curso')
                    ->where('ID_Pedido_Curso', $id_pedido_curso)
                    ->first();
                $usuarioCompleto = DB::table('entidad')
                    ->where('ID_Entidad', $ID_Entidad->ID_Entidad)
                    ->first();
                Log::error('usuarioCompleto: ' . print_r($usuarioCompleto, true));
                $phoneNumber = $usuarioCompleto->Nu_Celular_Entidad ?? $usuarioCompleto->Nu_Celular_Contacto;
                Log::error('phoneNumber: ' . $phoneNumber);
                // Validar y limpiar datos antes de enviar a Moodle
                $original_username = trim($result->No_Nombres_Apellidos);
                $password = $this->ciDecrypt($result->No_Password);

                if ($password === false) {
                    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_-=+;:,.?';
                    $length = 12;
                    $password = '';
                    for ($i = 0; $i < $length; $i++) {
                        $randomIndex = ord(random_bytes(1)) % strlen($chars);
                        $password .= $chars[$randomIndex];
                    }
                }
                
                $nombres = trim($result->No_Nombres_Apellidos);
                $email = trim($result->No_Usuario);
                
                Log::error('Datos del usuario: ' . json_encode([
                    'original_username' => $original_username,
                    'password' => $password,
                    'nombres' => $nombres,
                    'email' => $email,
                ]));

                // Validaciones básicas
                if (empty($original_username) || empty($password) || empty($nombres)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Datos de usuario incompletos'
                    ], 400);
                }

                // Validar formato de email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Formato de email inválido: ' . $email
                    ], 400);
                }

                // Limpiar y validar el email primero
                $email = strtolower(trim($email));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Email inválido después de limpieza: ' . $email
                    ], 400);
                }

                // Crear username más seguro (solo letras y números)
                $username = $this->generateSafeUsername($email);

                // Crear contraseña más segura (solo letras y números)
                $cleaned_password = $this->generateSafePassword($password);

                // Separar nombres y apellidos con más validación
                $nombres_array = explode(' ', $nombres);
                $firstname = $this->cleanString(isset($nombres_array[0]) ? trim($nombres_array[0]) : 'Usuario');
                $lastname = $this->cleanString(isset($nombres_array[1]) ? trim(implode(' ', array_slice($nombres_array, 1))) : 'Apellido');

                // Si no hay apellido válido, usar algo simple
                if (empty($lastname) || strlen($lastname) < 2) {
                    $lastname = 'Usuario';
                }

                // Asegurar longitudes mínimas y máximas
                $username = $this->validateLength($username, 3, 20);
                $firstname = $this->validateLength($firstname, 2, 50);
                $lastname = $this->validateLength($lastname, 2, 50);

                // Validar que todos los campos requeridos estén presentes antes de crear el array
                if (empty($username)) {
                    $username = 'user' . rand(1000, 9999);
                }
                if (empty($cleaned_password)) {
                    // Generar contraseña que cumpla requisitos de Moodle: al menos 8 caracteres, 1 número, 1 mayúscula, 1 minúscula, 1 especial
                    $cleaned_password = 'TempPass#' . rand(1000, 9999) . '!';
                }
                if (empty($firstname)) {
                    $firstname = 'Usuario';
                }
                if (empty($lastname)) {
                    $lastname = 'Usuario';
                }

                // Validaciones finales específicas para Moodle
                if (strlen($username) < 3 || strlen($username) > 100) {
                    $username = 'user' . rand(1000, 9999);
                }
                if (strlen($firstname) < 1 || strlen($firstname) > 100) {
                    $firstname = 'Usuario';
                }
                if (strlen($lastname) < 1 || strlen($lastname) > 100) {
                    $lastname = 'Usuario';
                }
                if (strlen($cleaned_password) < 8) {
                    // Generar contraseña que cumpla requisitos de Moodle
                    $cleaned_password = 'TempPass#' . rand(1000, 9999) . '!';
                }

                $arrPost = [
                    'username'     => $username,
                    'password'     => $cleaned_password,
                    'firstname'    => $firstname,
                    'lastname'     => $lastname,
                    'email'        => $email,
                    'auth'         => 'manual',
                    'lang'         => 'es',
                ];

                // Log para debug - verificar que todos los campos estén presentes
                Log::info('Array para Moodle creado:', $arrPost);

                // Verificar específicamente cada clave requerida
                $requiredKeys = ['username', 'password', 'firstname', 'lastname', 'email'];
                foreach ($requiredKeys as $key) {
                    if (!array_key_exists($key, $arrPost)) {
                        Log::error("Clave faltante en arrPost: $key");
                        return response()->json([
                            'status' => 'error',
                            'message' => "Clave faltante en datos de usuario: $key"
                        ], 400);
                    }
                    if (empty($arrPost[$key])) {
                        Log::warning("Clave vacía en arrPost: $key = " . ($arrPost[$key] ?? 'NULL'));
                    }
                }

                // Log detallado para debug
                Log::error('Datos limpiados para Moodle: ' . json_encode($arrPost));

                // Verificar cada campo individualmente
                $this->validateMoodleFields($arrPost);

                // Crear usuario y cursos para moodle
                $response_usuario_moodle = $this->createUser($arrPost);

                // Log de respuesta de Moodle
                Log::error('Respuesta de Moodle: ' . json_encode($response_usuario_moodle));
                Log::error('Respuesta de Moodle (var_dump): ' . print_r($response_usuario_moodle, true));

                if ($response_usuario_moodle['status'] == 'success') {
                    // ✅ Usar el username real de Moodle si el usuario ya existía, sino usar el generado
                    $moodle_username = $response_usuario_moodle['username'] ?? $username;
                    
                    // ✅ Usar la contraseña de la respuesta si está disponible (para usuarios existentes actualizados)
                    // Si no, usar la contraseña original que se preparó
                    $moodle_password = $response_usuario_moodle['password'] ?? $cleaned_password;
                    
                    // Log para debug - VERIFICAR VALORES
                    Log::error("=== DEBUG: ASIGNACIÓN DE VARIABLES ===");
                    Log::error("response_usuario_moodle completo: " . json_encode($response_usuario_moodle));
                    Log::error("response_usuario_moodle['username']: " . ($response_usuario_moodle['username'] ?? 'NO DEFINIDO'));
                    Log::error("username (generado): {$username}");
                    Log::error("moodle_username (asignado): {$moodle_username}");
                    Log::error("Password de respuesta: " . ($response_usuario_moodle['password'] ?? 'NO ENCONTRADO'));
                    Log::error("cleaned_password: {$cleaned_password}");
                    Log::error("moodle_password (asignado): {$moodle_password}");
                    Log::error("¿Coinciden las contraseñas? " . ($moodle_password === $cleaned_password ? 'SÍ' : 'NO'));
                    
                    // Si el usuario ya existía, usar su username real; si es nuevo, usar el generado
                    if (isset($response_usuario_moodle['user_exists']) && $response_usuario_moodle['user_exists']) {
                        Log::info("Usuario existente en Moodle, usando username real: {$moodle_username}");
                    } else {
                        Log::info("Usuario nuevo en Moodle, usando username generado: {$moodle_username}");
                    }
                    
                    // Buscar el usuario usando el username correcto (real o generado)
                    // ✅ FORZAR el uso del username de la respuesta si el usuario existe
                    if (isset($response_usuario_moodle['user_exists']) && $response_usuario_moodle['user_exists']) {
                        // Si el usuario ya existía, SIEMPRE usar el username de la respuesta
                        if (!empty($response_usuario_moodle['username'])) {
                            $moodle_username = $response_usuario_moodle['username'];
                            Log::error("✅ FORZADO: Usando username real de usuario existente: {$moodle_username}");
                        } else {
                            Log::error("⚠️ ERROR: Usuario existe pero no se encontró username en respuesta");
                        }
                    }
                    
                    // Verificación final
                    if (empty($moodle_username)) {
                        $moodle_username = $response_usuario_moodle['username'] ?? $username;
                        Log::error("⚠️ ADVERTENCIA: moodle_username estaba vacío, se reasignó a: {$moodle_username}");
                    }
                    
                    // ✅ Asegurar que $arrParams esté inicializado correctamente
                    $arrParams = [
                        'criteria' => [
                            [
                                'key' => 'username',
                                'value' => $moodle_username
                            ]
                        ]
                    ];
                    
                    // Log para verificar el valor que se va a usar
                    Log::error("=== ANTES DE BUSCAR USUARIO EN MOODLE ===");
                    Log::error("moodle_username FINAL a buscar: {$moodle_username}");
                    Log::error("username generado (NO usar): {$username}");
                    Log::error("arrParams completo: " . json_encode($arrParams));
                    Log::error("Verificación: arrParams['criteria'][0]['value'] = " . $arrParams['criteria'][0]['value']);
                    
                    // Set No_Usuario to $moodle_username (username real de Moodle)
                    // ✅ Usar la contraseña correcta (la de la respuesta si existe, sino la original)
                    $this->setUsuarioModdle(
                        $moodle_username,
                        $this->ciEncrypt($moodle_password),
                        $id
                    );
                    
                    $response_usuario = $this->getUser($arrParams);

                    if ($response_usuario['status'] == 'success') {
                        $result_usuario = $response_usuario['response'];
                        $id_usuario = $result_usuario['id'];

                        $arrParamsCurso = [
                            'id_usuario' => $id_usuario,
                        ];

                        $response_curso = $this->crearCursoUsuario($arrParamsCurso);

                        if ($response_curso['status'] != 'success') {
                            $this->actualizarPedido(['ID_Pedido_Curso' => $id_pedido_curso], ['Nu_Estado_Usuario_Externo' => '3']);

                            return response()->json([
                                'status' => 'error',
                                'message' => 'Usuario creado pero error al asignar curso: ' . ($response_curso['message'] ?? 'Error desconocido')
                            ], 500);
                        } else {
                            $this->actualizarPedido(['ID_Pedido_Curso' => $id_pedido_curso], ['Nu_Estado_Usuario_Externo' => '2']);

                            // Log para verificar credenciales antes de enviar
                            Log::info('=== CREDENCIALES A ENVIAR ===');
                            Log::info('Username Moodle: ' . $moodle_username);
                            Log::info('Password a enviar (longitud): ' . strlen($moodle_password) . ' caracteres');
                            Log::info('Password a enviar (valor completo): ' . $moodle_password);
                            Log::info('Email: ' . $email);
                            Log::info('Password usado en arrPost: ' . ($arrPost['password'] ?? 'NO DEFINIDO'));
                            Log::info('¿Coinciden las contraseñas? ' . ($moodle_password === ($arrPost['password'] ?? '') ? 'SÍ' : 'NO'));
                            
                            // ✅ Verificar si la contraseña se actualizó correctamente
                            $password_updated = $response_usuario_moodle['password_updated'] ?? true;
                            
                            // Si el usuario existía y la contraseña NO se actualizó, no enviar credenciales nuevas
                            // porque la contraseña que tenemos no es la real del usuario
                            if (isset($response_usuario_moodle['user_exists']) && 
                                $response_usuario_moodle['user_exists'] && 
                                !$password_updated) {
                                
                                Log::warning('⚠️ No se enviarán credenciales: Usuario existe pero contraseña no se pudo actualizar por permisos');
                                
                                return response()->json([
                                    'success' => true,
                                    'status' => 'warning',
                                    'message' => 'Usuario ya existe en Moodle pero no se pudo actualizar la contraseña por falta de permisos. El usuario debe usar su contraseña actual o solicitar recuperación de contraseña.',
                                    'data' => [
                                        'original_username' => $original_username,
                                        'moodle_username' => $moodle_username,
                                        'moodle_id' => $id_usuario,
                                        'user_existed' => true,
                                        'password_updated' => false,
                                        'warning' => 'Las credenciales no se enviaron porque la contraseña no se pudo actualizar. El usuario debe usar su contraseña actual en Moodle.'
                                    ]
                                ]);
                            }
                            
                            // ✅ Enviar credenciales por email y WhatsApp usando el username y password correctos de Moodle
                            $this->enviarCredencialesMoodle(
                                $moodle_username,
                                $moodle_password, // ✅ Usar la contraseña correcta
                                $email,
                                $nombres,
                                $phoneNumber
                            );

                            return response()->json([
                                'success' => true,
                                'status' => 'success',
                                'message' => 'Usuario y curso creados exitosamente. Credenciales enviadas por email y WhatsApp.',
                                'data' => [
                                    'original_username' => $original_username,
                                    'moodle_username' => $moodle_username, // ✅ Usar el username real de Moodle
                                    'moodle_id' => $id_usuario,
                                    'moodle_password' => $moodle_password, // ✅ Usar la contraseña correcta (no cleaned_password)
                                    'user_existed' => $response_usuario_moodle['user_exists'] ?? false,
                                    'password_updated' => $password_updated
                                ]
                            ]);
                        }
                    } else {
                        $this->actualizarPedido(['ID_Pedido_Curso' => $id_pedido_curso], ['Nu_Estado_Usuario_Externo' => '3']);

                        return response()->json([
                            'status' => 'error',
                            'message' => 'Usuario creado pero no se pudo recuperar: ' . ($response_usuario['message'] ?? 'Error desconocido')
                        ], 500);
                        
                    }
                } else {
                    $this->actualizarPedido(['ID_Pedido_Curso' => $id_pedido_curso], ['Nu_Estado_Usuario_Externo' => '2']);

                    $error_message = 'Error al crear usuario en Moodle';
                    if (isset($response_usuario_moodle['message'])) {
                        $error_message .= ': ' . $response_usuario_moodle['message'];
                    }
                    
                    // Crear array de debug con datos seguros
                    $debugData = [
                        'No_Usuario' => $result->usuario_moodle == "" ? $result->No_Usuario : $result->usuario_moodle,
                        'No_Password' => $this->ciDecrypt($result->No_Password),
                        'username' => $username ?? 'No generado',
                        'firstname' => $firstname ?? 'No generado',
                        'lastname' => $lastname ?? 'No generado',
                        'email' => $email ?? 'No disponible'
                    ];

                    // Enviar credenciales por email y WhatsApp (usuario ya existe)
                    $this->enviarCredencialesMoodle(
                        $username,
                        $cleaned_password,
                        $email,
                        $nombres,
                        $phoneNumber
                    );

                    return response()->json([
                        'status' => 'error',
                        'success' => true,
                        'message' => "El usuario ya existe en Moodle. Credenciales enviadas por email y WhatsApp.",
                        'data' => $debugData,
                        'moodle_response' => $response_usuario_moodle
                    ], 200);
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudieron obtener los datos del usuario: ' . ($response_usuario_bd['message'] ?? 'Error desconocido')
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en crearUsuarioCursosMoodle: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear usuario en Moodle: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera un username ultra seguro para Moodle
     */
    private function generateSafeUsername($email)
    {
        try {
            // Validar que el email no esté vacío
            if (empty($email)) {
                Log::warning('Email vacío en generateSafeUsername, usando fallback');
                return 'user' . rand(1000, 9999);
            }

            $email_parts = explode('@', $email);
            $base = isset($email_parts[0]) ? $email_parts[0] : 'user';

            // Limpiar el nombre: solo letras y números
            $clean = preg_replace('/[^a-zA-Z0-9]/', '', $base);

            // Si el nombre es muy corto o vacío, usar 'user' como base
            if (empty($clean) || strlen($clean) < 3) {
                $clean = 'user';
            } else {
                $clean = strtolower(substr($clean, 0, 10)); // Tomar máximo 10 caracteres del nombre
            }

            // Generar string aleatorio (4 caracteres alfanuméricos)
            $randomChars = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 4);

            // Construir el username final (max 20 chars)
            $username = $clean . $randomChars;

            // Asegurar que no exceda 20 caracteres
            $finalUsername = substr($username, 0, 20);
            
            Log::info('Username generado: ' . $finalUsername . ' desde email: ' . $email);
            return $finalUsername;
        } catch (\Exception $e) {
            Log::error('Error en generateSafeUsername: ' . $e->getMessage());
            return 'user' . rand(1000, 9999);
        }
    }

    /**
     * Genera una contraseña ultra segura para Moodle
     */
    private function generateSafePassword($password)
    {
        // Primero intentar limpiar la contraseña original
        $clean = preg_replace('/[<>"\'\\\]/', '', $password);

        // Si es muy corta o tiene caracteres problemáticos, generar nueva que cumpla requisitos de Moodle
        if (strlen($clean) < 8 || preg_match('/[^\w\d!@#%&*]/', $clean)) {
            return 'TempPass#' . rand(1000, 9999) . '!';
        }

        // Verificar que tenga al menos un caracter especial
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $clean)) {
            return $clean . '#' . rand(10, 99) . '!';
        }

        return $clean;
    }

    /**
     * Limpia strings para Moodle
     */
    private function cleanString($string)
    {
        // Remover caracteres especiales peligrosos
        $clean = preg_replace('/[<>"\'\\\&]/', '', $string);
        $clean = trim($clean);

        // Solo letras, números y espacios (más restrictivo para Moodle)
        $clean = preg_replace('/[^a-zA-Z0-9\s]/', '', $clean);
        
        // Remover espacios múltiples
        $clean = preg_replace('/\s+/', ' ', $clean);
        
        // Trim final
        $clean = trim($clean);

        return $clean;
    }

    /**
     * Valida longitud de strings
     */
    private function validateLength($string, $min, $max)
    {
        if (strlen($string) < $min) {
            return str_pad($string, $min, 'x');
        }
        if (strlen($string) > $max) {
            return substr($string, 0, $max);
        }
        return $string;
    }

    /**
     * Valida campos específicos de Moodle
     */
    private function validateMoodleFields($arrPost)
    {
        $errors = [];

        // Validar username
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $arrPost['username'])) {
            $errors[] = 'Username contiene caracteres inválidos';
        }

        // Validar email
        if (!filter_var($arrPost['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        }

        // Validar nombres
        if (empty($arrPost['firstname']) || strlen($arrPost['firstname']) < 2) {
            $errors[] = 'Firstname muy corto';
        }

        if (empty($arrPost['lastname']) || strlen($arrPost['lastname']) < 2) {
            $errors[] = 'Lastname muy corto';
        }

        // Validar contraseña
        if (strlen($arrPost['password']) < 8) {
            $errors[] = 'Password muy corto';
        }

        if (!empty($errors)) {
            Log::error('Errores de validación: ' . implode(', ', $errors));
        }

        return $errors;
    }

    /**
     * Obtiene resultados de validación para debug
     */
    private function getValidationResults($arrPost)
    {
        $results = [];
        
        if (isset($arrPost['username'])) {
            $results['username_length'] = strlen($arrPost['username']);
            $results['username_chars'] = preg_match('/^[a-zA-Z0-9._-]+$/', $arrPost['username']) ? 'valid' : 'invalid';
        }
        
        if (isset($arrPost['email'])) {
            $results['email_valid'] = filter_var($arrPost['email'], FILTER_VALIDATE_EMAIL) ? 'valid' : 'invalid';
        }
        
        if (isset($arrPost['firstname'])) {
            $results['firstname_length'] = strlen($arrPost['firstname']);
        }
        
        if (isset($arrPost['lastname'])) {
            $results['lastname_length'] = strlen($arrPost['lastname']);
        }
        
        if (isset($arrPost['password'])) {
            $results['password_length'] = strlen($arrPost['password']);
            $results['password_chars'] = preg_match('/^[a-zA-Z0-9!@#$%&*._-]+$/', $arrPost['password']) ? 'valid' : 'invalid';
        }
        
        return $results;
    }

    /**
     * @OA\Post(
     *     path="/cursos/enviar-email-moodle/{id}/{ID_Pedido_Curso}",
     *     tags={"Cursos"},
     *     summary="Enviar email de usuario Moodle",
     *     description="Envía las credenciales de Moodle al usuario por email y WhatsApp",
     *     operationId="enviarEmailUsuarioMoodle",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="ID_Pedido_Curso", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Email enviado exitosamente"),
     *     @OA\Response(response=404, description="Entidad no encontrada")
     * )
     */
    public function enviarEmailUsuarioMoodle($id, $ID_Pedido_Curso)
    {
        try {
            $id_pedido_curso = $ID_Pedido_Curso;
            
            // Buscar usuario
            $response_usuario_bd = $this->getUsuario($id);
            if ($response_usuario_bd['status'] == 'success') {
                $result = $response_usuario_bd['result'][0];
                
                // Enviar correo con las credenciales
                $entidad = $this->getEntidadByIdPedido($id_pedido_curso);
                if ($entidad) {
                    $idEntidad = $entidad->ID_Entidad;
                    $idPais    = $entidad->ID_Pais;
                    $telefono  = $entidad->Nu_Celular_Entidad;
                    $telefono  = preg_replace('/\s+/', '', $telefono);
                    $prefijoPais = $this->getPrefijoPais($idPais);
                    
                    if ($prefijoPais) {
                        $telefono = $prefijoPais->Nu_Prefijo . $telefono . "@c.us";
                    } else {
                        $telefono = '51' . $telefono; // Default to Peru if no prefix found
                    }
                    
                    $message = "Hola, {$result->No_Nombres_Apellidos},\n\n";
                    $message .= "Tu cuenta en ProBusiness ha sido creada exitosamente.\n\n";
                    $message .= "Usuario: " . (isset($result->usuario_moodle) && $result->usuario_moodle ? $result->usuario_moodle : $result->No_Usuario) . "\n";
                    $message .= "Contraseña: {$this->ciDecrypt($result->No_Password)}\n\n";
                    $message .= "Puedes acceder a tu cuenta en el siguiente enlace: https://aulavirtual.probusiness.pe/login/\n\n";
                    $mensaje = "El día del inicio del curso, te agregaremos a un grupo de whatsapp por donde compartiremos los links de acceso al zoom, los materiales de trabajo y las grabaciones de las clases dictadas.\n\n";
                    $message .= "Saludos,\nEl equipo de ProBusiness";
                    
                    $this->sendMessageVentas($message, $telefono);
                    $this->sendMessageVentas($mensaje, $telefono, 2);
                } else {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'No se encontró la entidad asociada al pedido.',
                    ], 404);
                }

                // Enviar email
                $data_email["email"]    = $result->usuario_moodle ? $result->usuario_moodle : $result->No_Usuario;
                $data_email["password"] = $this->ciDecrypt($result->No_Password);
                $data_email["name"]     = $result->No_Nombres_Apellidos;
                
                // Usar Laravel Mail en lugar de CodeIgniter email library
                try {
                    Mail::send('emails.cuenta_moodle', $data_email, function($message) use ($result) {
                        $message->from('noreply@lae.one', 'ProBusiness');
                        $message->to($result->No_Usuario);
                        $message->subject('🎉 Bienvenido al curso');
                    });

                    return response()->json([
                        'status'  => 'success',
                        'message' => 'Se envío email',
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error al enviar email: ' . $e->getMessage());
                    return response()->json([
                        'status'             => 'error',
                        'message'            => 'No se pudo enviar email, inténtelo más tarde.',
                        'error_message_mail' => $e->getMessage(),
                    ], 500);
                }
            } else {
                return response()->json($response_usuario_bd, 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en enviarEmailUsuarioMoodle: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al enviar email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/cursos/clientes/{id}",
     *     tags={"Cursos"},
     *     summary="Actualizar datos de cliente",
     *     description="Actualiza los datos personales de un cliente de cursos",
     *     operationId="actualizarDatosCliente",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="nombres", type="string"),
     *             @OA\Property(property="dni", type="string"),
     *             @OA\Property(property="sexo", type="integer"),
     *             @OA\Property(property="red_social", type="integer"),
     *             @OA\Property(property="correo", type="string"),
     *             @OA\Property(property="id_pais", type="integer"),
     *             @OA\Property(property="whatsapp", type="string"),
     *             @OA\Property(property="id_departamento", type="integer"),
     *             @OA\Property(property="nacimiento", type="string", format="date"),
     *             @OA\Property(property="id_provincia", type="integer"),
     *             @OA\Property(property="id_distrito", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cliente actualizado exitosamente")
     * )
     */
    public function actualizarDatosCliente(Request $request,$id)
    {
        try {
            $id_entidad = $id;
            $data = [
                'No_Entidad'             => $request->input('nombres'),
                'Nu_Documento_Identidad' => $request->input('dni'),
                'Nu_Tipo_Sexo'           => $request->input('sexo'),
                'Nu_Como_Entero_Empresa' => $request->input('red_social'),
                'Txt_Email_Entidad'      => $request->input('correo'),
                'ID_Pais'                => $request->input('id_pais'),
                'Nu_Celular_Entidad'     => $request->input('whatsapp'),
                'ID_Departamento'        => $request->input('id_departamento'),
                'Fe_Nacimiento'          => $request->input('nacimiento'),
                'ID_Provincia'           => $request->input('id_provincia'),
                'ID_Distrito'            => $request->input('id_distrito'),
            ];
            
            Log::info('ID_Entidad: ' . $id_entidad);
            Log::info('Datos recibidos del frontend: ' . json_encode($request->all()));
            Log::info('Datos mapeados para actualizar: ' . json_encode($data));
            
            $result = $this->actualizarDatosClienteModel($id_entidad, $data);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error en actualizarDatosCliente: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar datos del cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    // Métodos auxiliares que necesitan ser implementados o adaptados
    private function getUsuario($id)
    {
        try {
            $usuario = DB::table('usuario as u')
                ->join('entidad as e', 'u.ID_Entidad', '=', 'e.ID_Entidad')
                ->where('u.ID_Usuario', $id)
                ->select([
                    'u.ID_Usuario',
                    'u.No_Usuario',
                    'u.No_Password',
                    'u.usuario_moodle',
                    'e.No_Entidad as No_Nombres_Apellidos',
                    'e.ID_Entidad'
                ])
                ->first();

            if ($usuario) {
                return [
                    'status' => 'success',
                    'result' => [$usuario]
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Usuario no encontrado'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error en getUsuario: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Error al obtener usuario: ' . $e->getMessage()
            ];
        }
    }

    private function setUsuarioModdle($username, $password, $id)
    {
        // Implementar actualización de usuario con datos de Moodle
        DB::table('usuario')
            ->where('ID_Usuario', $id)
            ->update([
                'usuario_moodle' => $username,
                'No_Password' => $password
            ]);
    }

    private function actualizarPedido($where, $data)
    {
        DB::table('pedido_curso')->where($where)->update($data);
    }

 

    private function getEntidadByIdPedido($id_pedido)
    {
        return DB::table('pedido_curso as pc')
            ->join('entidad as e', 'pc.ID_Entidad', '=', 'e.ID_Entidad')
            ->where('pc.ID_Pedido_Curso', $id_pedido)
            ->select('e.*')
            ->first();
    }

    private function getPrefijoPais($id_pais)
    {
        return DB::table('pais')
            ->where('ID_Pais', $id_pais)
            ->select('Nu_Prefijo')
            ->first();
    }

   
    private function actualizarDatosClienteModel($id_entidad, $data)
    {
        try {
            DB::table('entidad')
                ->where('ID_Entidad', $id_entidad)
                ->update($data);
            
            return ['status' => 'success', 'message' => 'Datos actualizados correctamente'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Error al actualizar: ' . $e->getMessage()];
        }
    }

    /**
     * @OA\Post(
     *     path="/cursos/{idPedidoCurso}/constancia",
     *     tags={"Cursos"},
     *     summary="Generar constancia de pedido",
     *     description="Genera y envía la constancia de un pedido de curso",
     *     operationId="generarConstanciaPedido",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idPedidoCurso", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Constancia generada exitosamente"),
     *     @OA\Response(response=404, description="Pedido no encontrado"),
     *     @OA\Response(response=400, description="Pedido no confirmado o sin teléfono")
     * )
     *
     * Genera y envía la constancia de un pedido de curso
     */
    public function generarConstanciaPedido($idPedidoCurso)
    {
        try {
            
            $idPedidoCurso = $idPedidoCurso;

            // Obtener datos del pedido de curso
            $pedidoCurso = DB::table('pedido_curso as pc')
                ->join('entidad as e', 'pc.ID_Entidad', '=', 'e.ID_Entidad')
                ->where('pc.ID_Pedido_Curso', $idPedidoCurso)
                ->select(
                    'pc.*',
                    'e.No_Entidad',
                    'e.Nu_Celular_Entidad',
                    'e.Txt_Email_Entidad'
                )
                ->first();

            if (!$pedidoCurso) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pedido de curso no encontrado'
                ], 404);
            }

            // Validar que el pedido esté confirmado
            if ($pedidoCurso->Nu_Estado_Usuario_Externo != 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'El pedido de curso debe estar confirmado para generar la constancia'
                ], 400);
            }

            // Validar que tenga teléfono
            if (empty($pedidoCurso->Nu_Celular_Entidad)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El pedido no tiene un número de teléfono registrado'
                ], 400);
            }

            // Despachar el job
            \App\Jobs\SendConstanciaCurso::dispatch(
                $pedidoCurso->Nu_Celular_Entidad,
                $pedidoCurso
            )->onQueue('emails');

            return response()->json([
                'success' => true,
                'message' => 'La constancia se está generando y enviando. Recibirás una notificación cuando esté lista.'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en generarConstanciaPedido: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar la constancia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envía las credenciales de Moodle por email y WhatsApp
     * 
     * @param string $username Usuario de Moodle
     * @param string $password Contraseña de Moodle
     * @param string $email Email del usuario
     * @param string $nombre Nombre completo del usuario
     * @param string $phoneNumber Número de teléfono para WhatsApp
     * @return void
     */
    private function enviarCredencialesMoodle($username, $password, $email, $nombre, $phoneNumber = null)
    {
        try {
            // Log para verificar credenciales recibidas
            Log::info('=== ENVIAR CREDENCIALES MOODLE ===');
            Log::info('Username recibido: ' . $username);
            Log::info('Password recibido (longitud): ' . strlen($password) . ' caracteres');
            Log::info('Password recibido (valor completo): ' . $password);
            Log::info('Email: ' . $email);
            
            // URL de Moodle desde configuración o variable de entorno
            $moodleUrl = env('MOODLE_URL', 'https://aulavirtual.probusiness.pe/login/index.php');
            
            // Rutas de los logos
            $logo_header = public_path('storage/logo_icons/logo_header.png');
            $logo_footer = public_path('storage/logo_icons/logo_footer.png');

            // Enviar email con las credenciales
            try {
                Mail::to($email)->send(
                    new MoodleCredentialsMail(
                        $username,
                        $password,
                        $email,
                        $nombre,
                        $moodleUrl,
                        $logo_header,
                        $logo_footer
                    )
                );
                $whatsappMessage = "🎓 *Hola {$nombre}!*\n\n";
                $whatsappMessage .= "Te damos la bienvenida a nuestra plataforma de cursos.\n\n";
                $whatsappMessage .= "📋 *Tus credenciales de acceso:*\n\n";
                $whatsappMessage .= "👤 *Usuario:* {$username}\n";
                $whatsappMessage .= "🔑 *Contraseña:* {$password}\n";
                $whatsappMessage .= "🌐 *Plataforma:* {$moodleUrl}\n\n";
                $whatsappMessage .= "⚠️ _Por seguridad, te recomendamos cambiar tu contraseña al primer ingreso._\n\n";
                $whatsappMessage .= "¡Éxitos en tu aprendizaje! 🚀\n\n";
                $whatsappMessage .= "_Equipo Probusiness_";
                //send whatsapp
                //trim phone number
                $phoneNumber = trim($phoneNumber);
                //format number if not has 51
                if (strlen($phoneNumber) == 9) {
                    $phoneNumber = '51' . $phoneNumber.'@c.us';
                } else {
                    $phoneNumber = $phoneNumber.'@c.us';
                }
                $this->sendMessageCurso($whatsappMessage, $phoneNumber);
                Log::info('Email de credenciales Moodle enviado exitosamente', [
                    'email' => $email,
                    'username' => $username
                ]);
            } catch (\Exception $e) {
                Log::error('Error al enviar email de credenciales Moodle: ' . $e->getMessage(), [
                    'email' => $email,
                    'trace' => $e->getTraceAsString()
                ]);
            }

          
        } catch (\Exception $e) {
            Log::error('Error general al enviar credenciales Moodle: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    /**
     * @OA\Get(
     *     path="/cursos/exportar-excel",
     *     tags={"Cursos"},
     *     summary="Exportar cursos a Excel",
     *     description="Exporta la lista de cursos a un archivo Excel con los mismos filtros que el listado",
     *     operationId="exportarExcel",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="campanas", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="fechaInicio", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="fechaFin", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="estados_pago", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="tipos_curso", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Archivo Excel generado exitosamente")
     * )
     *
     * Exportar cursos a Excel usando los mismos filtros que index
     */
    public function exportarExcel(Request $request)
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

            // Obtener los mismos filtros que usa el método index
            $search = $request->get('search', '');
            $campanas = $request->get('campanas', '');
            $fechaInicio = $request->get('fechaInicio', '');
            $fechaFin = $request->get('fechaFin', '');
            $estadoPago = $request->get('estados_pago', '');
            $tipoCurso = $request->get('tipos_curso', '');
            $sobrepagado = $request->get('sobrepagado', '');

            // Construir la misma consulta que el método index pero sin paginación
            $query = DB::table('pedido_curso AS PC')
                ->select([
                    'PC.*',
                    'CLI.Fe_Nacimiento',
                    'CLI.Nu_Como_Entero_Empresa',
                    'CLI.No_Otros_Como_Entero_Empresa',
                    'DIST.No_Distrito',
                    'PROV.No_Provincia',
                    'DEP.No_Departamento',
                    'TDI.No_Tipo_Documento_Identidad_Breve',
                    'P.No_Pais',
                    'CLI.Nu_Tipo_Sexo',
                    'CLI.No_Entidad',
                    'CLI.Nu_Documento_Identidad',
                    'CLI.Nu_Celular_Entidad',
                    'CLI.Txt_Email_Entidad',
                    'CLI.Nu_Edad',
                    'M.No_Signo',
                    'USR.ID_Usuario',
                    'USR.No_Usuario',
                    'USR.No_Password',
                    'CAMP.Fe_Fin',
                    'CAMP.Fe_Inicio',
                    DB::raw('tipo_curso'),
                    DB::raw('(
                        SELECT COUNT(*)
                        FROM pedido_curso_pagos AS cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                        AND ccp.name = "ADELANTO"
                    ) AS pagos_count'),
                    DB::raw('(
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM pedido_curso_pagos AS cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                        AND ccp.name = "ADELANTO"
                    ) AS total_pagos')
                ])
                ->leftJoin('pais AS P', 'P.ID_Pais', '=', 'PC.ID_Pais')
                ->leftJoin('entidad AS CLI', 'CLI.ID_Entidad', '=', 'PC.ID_Entidad')
                ->leftJoin('tipo_documento_identidad AS TDI', 'TDI.ID_Tipo_Documento_Identidad', '=', 'CLI.ID_Tipo_Documento_Identidad')
                ->leftJoin('moneda AS M', 'M.ID_Moneda', '=', 'PC.ID_Moneda')
                ->leftJoin('usuario AS USR', 'USR.ID_Entidad', '=', 'CLI.ID_Entidad')
                ->leftJoin('campana_curso AS CAMP', 'CAMP.ID_Campana', '=', 'PC.ID_Campana')
                ->leftJoin('distrito AS DIST', 'DIST.ID_Distrito', '=', 'CLI.ID_Distrito')
                ->leftJoin('provincia AS PROV', 'PROV.ID_Provincia', '=', 'CLI.ID_Provincia')
                ->leftJoin('departamento AS DEP', 'DEP.ID_Departamento', '=', 'CLI.ID_Departamento')
                ->where('PC.ID_Empresa', $user->ID_Empresa);

            // Aplicar los mismos filtros que el método index
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('CLI.No_Entidad', 'like', "%$search%")
                        ->orWhere('CLI.Nu_Documento_Identidad', 'like', "%$search%")
                        ->orWhere('PC.ID_Pedido_Curso', 'like', "%$search%");
                });
            }

            if ($fechaInicio && $fechaFin) {
                $query->whereBetween('PC.Fe_Registro', [$fechaInicio, $fechaFin]);
            }

            if ($campanas && $campanas != '0') {
                $query->where('PC.ID_Campana', $campanas);
            }

            // Aplicar filtro de estado de pago
            if ($estadoPago && $estadoPago != '0') {
                $query->whereRaw('(
                    CASE 
                        WHEN (
                            SELECT IFNULL(SUM(cccp.monto), 0)
                            FROM pedido_curso_pagos AS cccp
                            JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                            WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                            AND ccp.name = "ADELANTO"
                        ) = 0 THEN "pendiente"
                        WHEN (
                            SELECT IFNULL(SUM(cccp.monto), 0)
                            FROM pedido_curso_pagos AS cccp
                            JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                            WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                            AND ccp.name = "ADELANTO"
                        ) < PC.Ss_Total AND (
                            SELECT IFNULL(SUM(cccp.monto), 0)
                            FROM pedido_curso_pagos AS cccp
                            JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                            WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                            AND ccp.name = "ADELANTO"
                        ) > 0 THEN "adelanto"
                        WHEN (
                            SELECT IFNULL(SUM(cccp.monto), 0)
                            FROM pedido_curso_pagos AS cccp
                            JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                            WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                            AND ccp.name = "ADELANTO"
                        ) = PC.Ss_Total THEN "pagado"
                        WHEN (
                            SELECT IFNULL(SUM(cccp.monto), 0)
                            FROM pedido_curso_pagos AS cccp
                            JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                            WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                            AND ccp.name = "ADELANTO"
                        ) > PC.Ss_Total THEN "sobrepagado"
                        ELSE "pendiente"
                    END
                ) = ?', [$estadoPago]);
            }

            // Filtro por tipo de curso
            if ($tipoCurso && $tipoCurso !== '') {
                $query->where('PC.tipo_curso', $tipoCurso);
            }

            // Ordenar por id descendente
            $query->orderBy('PC.ID_Pedido_Curso', 'desc');

            // Obtener todos los datos sin paginación
            $cursos = $query->get();

            // Procesar los datos de la misma manera que el método index
            $cursosProcessed = $cursos->map(function ($curso) {
                // Determinar el estado del pago
                $estado = 'pendiente';
                if ($curso->total_pagos == 0) {
                    $estado = 'pendiente';
                } elseif ($curso->total_pagos < $curso->Ss_Total && $curso->total_pagos > 0) {
                    $estado = 'adelanto';
                } elseif ($curso->total_pagos == $curso->Ss_Total) {
                    $estado = 'pagado';
                } elseif ($curso->total_pagos > $curso->Ss_Total) {
                    $estado = 'sobrepagado';
                }

                // Verificar si corresponde constancia (curso en vivo y fecha fin pasada)
                $fechaHoy = now()->toDateString();
                $fechaFin = $curso->Fe_Fin ?? null;
                $tipoCurso = $curso->tipo_curso ?? null;

                if ($tipoCurso == 1 && $fechaFin && strtotime($fechaHoy) > strtotime($fechaFin)) {
                    $estado = 'constancia';
                }

                // Obtener el nombre del mes de la campaña
                $mesNombre = '';
                if ($curso->Fe_Inicio) {
                    $mesNumero = date('n', strtotime($curso->Fe_Inicio));
                    $meses_es = [
                        1 => 'Enero',
                        2 => 'Febrero',
                        3 => 'Marzo',
                        4 => 'Abril',
                        5 => 'Mayo',
                        6 => 'Junio',
                        7 => 'Julio',
                        8 => 'Agosto',
                        9 => 'Septiembre',
                        10 => 'Octubre',
                        11 => 'Noviembre',
                        12 => 'Diciembre'
                    ];
                    $mesNombre = $meses_es[$mesNumero] ?? '';
                }

                return [
                    'ID_Pedido_Curso' => $curso->ID_Pedido_Curso,
                    'Fe_Registro' => DateHelper::formatDate($curso->Fe_Registro, '-', 0),
                    'Fe_Registro_Original' => $curso->Fe_Registro,
                    'No_Entidad' => $curso->No_Entidad,
                    'No_Tipo_Documento_Identidad_Breve' => $curso->No_Tipo_Documento_Identidad_Breve,
                    'Nu_Documento_Identidad' => $curso->Nu_Documento_Identidad,
                    'Nu_Celular_Entidad' => $curso->Nu_Celular_Entidad,
                    'Txt_Email_Entidad' => $curso->Txt_Email_Entidad,
                    'tipo_curso' => strval($curso->tipo_curso),
                    'ID_Campana' => $curso->ID_Campana,
                    'Nombre_Campana' => $mesNombre,
                    'ID_Usuario' => $curso->ID_Usuario,
                    'Nu_Estado_Usuario_Externo' => $curso->Nu_Estado_Usuario_Externo ?? 1,
                    'Ss_Total' => $curso->Ss_Total,
                    'Nu_Estado' => $curso->Nu_Estado,
                    'Fe_Fin' => DateHelper::formatDate($curso->Fe_Fin, '-', 0),
                    'Fe_Fin_Original' => $curso->Fe_Fin,
                    'pagos_count' => $curso->pagos_count,
                    'total_pagos' => $curso->total_pagos,
                    'estado_pago' => $estado,
                    'puede_constancia' => $curso->send_constancia=='SENDED'?true:false,
                    // Información adicional del cliente
                    'Fe_Nacimiento' => DateHelper::formatDate($curso->Fe_Nacimiento, '-', 0),
                    'Fe_Nacimiento_Original' => $curso->Fe_Nacimiento,
                    'Nu_Como_Entero_Empresa' => $curso->Nu_Como_Entero_Empresa,
                    'No_Otros_Como_Entero_Empresa' => $curso->No_Otros_Como_Entero_Empresa,
                    'No_Distrito' => $curso->No_Distrito,
                    'No_Provincia' => $curso->No_Provincia,
                    'No_Departamento' => $curso->No_Departamento,
                    'No_Pais' => $curso->No_Pais,
                    'Nu_Tipo_Sexo' => $curso->Nu_Tipo_Sexo,
                    'Nu_Edad' => $curso->Nu_Edad,
                    'No_Signo' => $curso->No_Signo,
                    'No_Usuario' => $curso->No_Usuario
                ];
            });

            // Generar nombre del archivo con timestamp
            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "cursos_export_{$timestamp}.xlsx";

            // Crear y descargar el archivo Excel
            return Excel::download(new CursosExport($cursosProcessed), $filename);

        } catch (\Exception $e) {
            Log::error('Error en exportarExcel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/cursos/{idPedido}/recordatorio-pago",
     *     tags={"Cursos"},
     *     summary="Enviar recordatorio de pago",
     *     description="Envía un recordatorio de pago por WhatsApp al cliente",
     *     operationId="enviarRecordatorioPago",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idPedido", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Recordatorio enviado exitosamente"),
     *     @OA\Response(response=404, description="Pedido no encontrado"),
     *     @OA\Response(response=400, description="Cliente sin teléfono registrado")
     * )
     *
     * Enviar recordatorio de pago por WhatsApp
     */
    public function enviarRecordatorioPago($idPedido)
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

            // Obtener datos del pedido con información del cliente y pagos
            $pedido = DB::table('pedido_curso AS PC')
                ->select([
                    'PC.ID_Pedido_Curso',
                    'PC.Ss_Total',
                    'CLI.No_Entidad',
                    'CLI.Nu_Celular_Entidad',
                    DB::raw('(
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM pedido_curso_pagos AS cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                        AND ccp.name = "ADELANTO"
                    ) AS total_pagos')
                ])
                ->leftJoin('entidad AS CLI', 'CLI.ID_Entidad', '=', 'PC.ID_Entidad')
                ->where('PC.ID_Pedido_Curso', $idPedido)
                ->first();

            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pedido no encontrado'
                ], 404);
            }

            // Validar que tenga número de teléfono
            if (empty($pedido->Nu_Celular_Entidad)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente no tiene número de teléfono registrado'
                ], 400);
            }

            // Calcular valores
            $importe = floatval($pedido->Ss_Total ?? 0);
            $adelanto = floatval($pedido->total_pagos ?? 0);
            $deuda = $importe - $adelanto;

            // Formatear números con 2 decimales
            $importeFormateado = number_format($importe, 2, '.', '');
            $adelantoFormateado = number_format($adelanto, 2, '.', '');
            $deudaFormateada = number_format($deuda, 2, '.', '');

            // Construir mensaje
            $nombreCliente = $pedido->No_Entidad ?? 'Cliente';
            $mensaje = "Buen día {$nombreCliente} 👋\n\n";
            $mensaje .= "Hoy estamos comenzando las clases 📚 recordarte que para poder gestionar las credenciales tienes que enviar la captura de la diferencia, quedo a la espera 🚢📦🌎✈️\n\n";
            $mensaje .= "Importe: S/ {$importeFormateado}\n";
            $mensaje .= "Adelanto: S/ {$adelantoFormateado}\n";
            $mensaje .= "Deuda: S/ {$deudaFormateada}";

            // Formatear número de teléfono
            $phoneNumber = trim($pedido->Nu_Celular_Entidad);
            // Remover caracteres no numéricos excepto el prefijo
            $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
            
            // Si tiene 9 dígitos, agregar prefijo 51
            if (strlen($phoneNumber) == 9) {
                $phoneNumber = '51' . $phoneNumber . '@c.us';
            } else {
                // Si ya tiene prefijo, solo agregar @c.us
                $phoneNumber = $phoneNumber . '@c.us';
            }

            // Enviar mensaje por WhatsApp usando sendMessageCurso
            $response = $this->sendMessageCurso($mensaje, $phoneNumber);

            if ($response && isset($response['status']) && $response['status']) {
                Log::info('Recordatorio de pago enviado exitosamente', [
                    'id_pedido' => $idPedido,
                    'cliente' => $nombreCliente,
                    'telefono' => $phoneNumber
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Recordatorio de pago enviado correctamente'
                ]);
            } else {
                $errorMessage = $response['response']['error'] ?? 'Error desconocido al enviar WhatsApp';
                Log::error('Error al enviar recordatorio de pago', [
                    'id_pedido' => $idPedido,
                    'response' => $response
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al enviar el recordatorio',
                    'error' => $errorMessage
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en enviarRecordatorioPago: ' . $e->getMessage(), [
                'id_pedido' => $idPedido,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar recordatorio de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/cursos/{idPedido}/instrucciones-password",
     *     tags={"Cursos"},
     *     summary="Enviar instrucciones de cambio de contraseña",
     *     description="Envía instrucciones para cambiar la contraseña por WhatsApp",
     *     operationId="enviarInstruccionesCambioPassword",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idPedido", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Instrucciones enviadas exitosamente"),
     *     @OA\Response(response=404, description="Pedido no encontrado"),
     *     @OA\Response(response=400, description="Cliente sin teléfono registrado")
     * )
     *
     * Enviar instrucciones para cambiar contraseña por WhatsApp
     */
    public function enviarInstruccionesCambioPassword($idPedido)
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

            // Obtener datos del pedido con información del cliente
            $pedido = DB::table('pedido_curso AS PC')
                ->select([
                    'PC.ID_Pedido_Curso',
                    'CLI.No_Entidad',
                    'CLI.Nu_Celular_Entidad'
                ])
                ->leftJoin('entidad AS CLI', 'CLI.ID_Entidad', '=', 'PC.ID_Entidad')
                ->where('PC.ID_Pedido_Curso', $idPedido)
                ->first();

            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pedido no encontrado'
                ], 404);
            }

            // Validar que tenga número de teléfono
            if (empty($pedido->Nu_Celular_Entidad)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente no tiene número de teléfono registrado'
                ], 400);
            }

            // Construir mensaje
            $nombreCliente = $pedido->No_Entidad ?? 'Cliente';
            $mensaje = "Hola {$nombreCliente} 👋\n\n";
            $mensaje .= "Para cambiar tu contraseña del aula virtual, sigue estos pasos:\n\n";
            $mensaje .= "1. Ingresa a: https://aulavirtual.probusiness.pe/login/forgot_password.php\n";
            $mensaje .= "2. Ingresa tu nombre de usuario o correo electrónico\n";
            $mensaje .= "3. Revisa tu correo para recibir las instrucciones\n\n";
            $mensaje .= "Si tienes alguna duda, no dudes en contactarnos.\n\n";
            $mensaje .= "¡Saludos! 🚀\n";
            $mensaje .= "_Equipo Probusiness_";

            // Formatear número de teléfono
            $phoneNumber = trim($pedido->Nu_Celular_Entidad);
            // Remover caracteres no numéricos excepto el prefijo
            $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
            
            // Si tiene 9 dígitos, agregar prefijo 51
            if (strlen($phoneNumber) == 9) {
                $phoneNumber = '51' . $phoneNumber . '@c.us';
            } else {
                // Si ya tiene prefijo, solo agregar @c.us
                $phoneNumber = $phoneNumber . '@c.us';
            }

            // Enviar mensaje por WhatsApp usando sendMessageCurso
            $response = $this->sendMessageCurso($mensaje, $phoneNumber);

            if ($response && isset($response['status']) && $response['status']) {
                Log::info('Instrucciones de cambio de contraseña enviadas exitosamente', [
                    'id_pedido' => $idPedido,
                    'cliente' => $nombreCliente,
                    'telefono' => $phoneNumber
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Instrucciones de cambio de contraseña enviadas correctamente'
                ]);
            } else {
                $errorMessage = $response['response']['error'] ?? 'Error desconocido al enviar WhatsApp';
                Log::error('Error al enviar instrucciones de cambio de contraseña', [
                    'id_pedido' => $idPedido,
                    'response' => $response
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al enviar las instrucciones',
                    'error' => $errorMessage
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en enviarInstruccionesCambioPassword: ' . $e->getMessage(), [
                'id_pedido' => $idPedido,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar instrucciones de cambio de contraseña',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
