<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\DateHelper;
use Tymon\JWTAuth\Facades\JWTAuth;

class CampaignController extends Controller
{
    private $table_campana_curso_dias = 'campana_curso_dias';

    /**
     * @OA\Get(
     *     path="/campaigns/{id}/students",
     *     tags={"Campañas"},
     *     summary="Obtener estudiantes de una campaña",
     *     description="Obtiene la lista de estudiantes asociados a una campaña de curso",
     *     operationId="getCampaignStudents",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="fechaInicio", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="fechaFin", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="estados_pago", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="tipos_curso", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Estudiantes obtenidos exitosamente"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function getStudents($id, Request $request)
    {
        try {
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            $search = $request->get('search', '');
            $fechaInicio = $request->get('fechaInicio', '');
            $fechaFin = $request->get('fechaFin', '');
            $estadoPago = $request->get('estados_pago', '');
            $tipoCurso = $request->get('tipos_curso', '');

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
                ->where('PC.ID_Campana', $id)
                ->where('PC.ID_Empresa', $user->ID_Empresa);

            // Aplicar filtros
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('CLI.No_Entidad', 'like', "%$search%")
                        ->orWhere('CLI.Nu_Documento_Identidad', 'like', "%$search%")
                        ->orWhere('PC.ID_Pedido_Curso', 'like', "%$search%")
                        // Agrega aquí más campos si quieres que el buscador sea más amplio
                    ;
                });
            }

            if ($fechaInicio && $fechaFin) {
                $query->whereBetween('PC.Fe_Registro', [$fechaInicio, $fechaFin]);
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
            $students = $query->offset($offset)->limit($perPage)->get();

            // Procesar los datos para agregar información adicional
            $studentsProcessed = $students->map(function ($student) {
                // Determinar el estado del pago
                $estado = 'pendiente';
                if ($student->total_pagos == 0) {
                    $estado = 'pendiente';
                } elseif ($student->total_pagos < $student->Ss_Total && $student->total_pagos > 0) {
                    $estado = 'adelanto';
                } elseif ($student->total_pagos == $student->Ss_Total) {
                    $estado = 'pagado';
                } elseif ($student->total_pagos > $student->Ss_Total) {
                    $estado = 'sobrepagado';
                }

                // Verificar si corresponde constancia (curso en vivo y fecha fin pasada)
                $fechaHoy = now()->toDateString();
                $fechaFin = $student->Fe_Fin ?? null;
                $tipoCurso = $student->tipo_curso ?? null;

                if ($tipoCurso == 1 && $fechaFin && strtotime($fechaHoy) > strtotime($fechaFin)) {
                    $estado = 'constancia';
                }
                return [
                    'ID_Pedido_Curso' => $student->ID_Pedido_Curso,
                    'Fe_Registro' => DateHelper::formatDate($student->Fe_Registro, '-', 0),
                    'Fe_Registro_Original' => $student->Fe_Registro,
                    'No_Entidad' => $student->No_Entidad,
                    'No_Tipo_Documento_Identidad_Breve' => $student->No_Tipo_Documento_Identidad_Breve,
                    'Nu_Documento_Identidad' => $student->Nu_Documento_Identidad,
                    'Nu_Celular_Entidad' => $student->Nu_Celular_Entidad,
                    'Txt_Email_Entidad' => $student->Txt_Email_Entidad,
                    'tipo_curso' => strval($student->tipo_curso),
                    'ID_Campana' => $student->ID_Campana,
                    'ID_Usuario' => $student->ID_Usuario,
                    'Nu_Estado_Usuario_Externo' => $student->Nu_Estado_Usuario_Externo ?? 1,
                    'Ss_Total' => $student->Ss_Total,
                    'Nu_Estado' => $student->Nu_Estado,
                    'Fe_Fin' => DateHelper::formatDate($student->Fe_Fin, '-', 0),
                    'Fe_Fin_Original' => $student->Fe_Fin,
                    'pagos_count' => $student->pagos_count,
                    'total_pagos' => $student->total_pagos,
                    'estado_pago' => $estado,
                    'puede_constancia' => $student->send_constancia=='SENDED'?true:false,
                    // Información adicional del cliente
                    'Fe_Nacimiento' => DateHelper::formatDate($student->Fe_Nacimiento, '-', 0),
                    'Fe_Nacimiento_Original' => $student->Fe_Nacimiento,
                    'Nu_Como_Entero_Empresa' => $student->Nu_Como_Entero_Empresa,
                    'No_Otros_Como_Entero_Empresa' => $student->No_Otros_Como_Entero_Empresa,
                    'No_Distrito' => $student->No_Distrito,
                    'No_Provincia' => $student->No_Provincia,
                    'No_Departamento' => $student->No_Departamento,
                    'No_Pais' => $student->No_Pais,
                    'Nu_Tipo_Sexo' => $student->Nu_Tipo_Sexo,
                    'Nu_Edad' => $student->Nu_Edad,
                    'No_Signo' => $student->No_Signo,
                    'No_Usuario' => $student->No_Usuario
                ];
            });
            $totalAmount = $studentsProcessed->sum('total_pagos');

            return response()->json([
                'success' => true,
                'data' => $studentsProcessed,
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
                'importe_total' => $totalAmount,
                'total_pedidos' => $totalRecords
            ]);
        } catch (\Exception $e) {
            Log::error('Error en getStudents: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los estudiantes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * @OA\Post(
     *     path="/campaigns",
     *     tags={"Campañas"},
     *     summary="Crear una nueva campaña",
     *     description="Crea una nueva campaña de curso con sus fechas y días seleccionados",
     *     operationId="storeCampaign",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"Fe_Inicio", "Fe_Fin", "Dias_Seleccionados"},
     *             @OA\Property(property="Fe_Inicio", type="string", format="date"),
     *             @OA\Property(property="Fe_Fin", type="string", format="date"),
     *             @OA\Property(property="Dias_Seleccionados", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Campaña creada exitosamente"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function store(Request $request)
    {
        try {
            // Validar los datos del request
            $request->validate([
                'Fe_Inicio' => 'required|date',
                'Fe_Fin' => 'required|date|after:Fe_Inicio',
                'Dias_Seleccionados' => 'required'
            ]);

            $fe_inicio = $request->Fe_Inicio;
            $fe_fin = $request->Fe_Fin;
            $dias = $request->Dias_Seleccionados;

            // Insertar campaña
            $data = [
                'Fe_Inicio'   => $fe_inicio,
                'Fe_Fin'      => $fe_fin,
                'Fe_Creacion' => now()
            ];

            $id = DB::table('campana_curso')->insertGetId($data);

            if ($id) {
                // Obtener la campaña recién creada
                $campana = DB::table('campana_curso as c')
                    ->select([
                        'c.ID_Campana',
                        'c.Fe_Creacion',
                        'c.Fe_Inicio',
                        'c.Fe_Fin',
                        DB::raw('MONTH(c.Fe_Inicio) as Mes_Numero'),
                        DB::raw('(SELECT COUNT(*) FROM pedido_curso p WHERE p.ID_Campana = c.ID_Campana) as cantidad_personas')
                    ])
                    ->where('c.ID_Campana', $id)
                    ->first();

                // Traduce el mes a español
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
                $no_campana = $meses_es[(int)$campana->Mes_Numero];

                // Armar array por posición (igual que en getCampanas)
                $data_row = [
                    $campana->ID_Campana,
                    $campana->Fe_Creacion,
                    $no_campana,
                    $campana->Fe_Inicio,
                    $campana->Fe_Fin,
                    $campana->cantidad_personas,
                    '<div>
                        <i class="fas fa-eye text-primary view-eye" style="cursor:pointer; padding:10px;"></i>
                        <i class="fas fa-trash text-danger" style="cursor:pointer; padding:10px;" onclick="borrarCampana(\'' . $campana->ID_Campana . '\')"></i>
                    </div>'
                ];

                // Eliminar días existentes e insertar nuevos días
                DB::table($this->table_campana_curso_dias)
                    ->where('id_campana', $id)
                    ->delete();

                $dias_array = $dias;

                if (is_array($dias_array)) {
                    foreach ($dias_array as $dia) {
                        Log::info('Día a insertar: ' . $dia);
                        Log::info('ID de campaña: ' . $id);

                        $data_dia = [
                            'id_campana' => $id,
                            'fecha' => $dia
                        ];

                        DB::table($this->table_campana_curso_dias)->insert($data_dia);
                    }
                }

                return response()->json([
                    'message' => 'Campaña registrada correctamente',
                    'success' => true,
                    'row' => $data_row // <-- array por posición
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudo registrar la campaña'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en crearCampana: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear la campaña: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/campaigns",
     *     tags={"Campañas"},
     *     summary="Listar campañas",
     *     description="Obtiene la lista de todas las campañas de cursos",
     *     operationId="getCampaigns",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Campañas obtenidas exitosamente")
     * )
     */
    public function index(Request $request)
    {
        try {

            // Obtener campañas desde la base de datos
            $campanas = DB::table('campana_curso as c')
                ->select([
                    'c.ID_Campana',
                    'c.Fe_Creacion',
                    'c.Fe_Inicio',
                    'c.Fe_Fin',
                    DB::raw('MONTH(c.Fe_Inicio) as Mes_Numero'),
                    DB::raw('(SELECT COUNT(*) FROM pedido_curso p WHERE p.ID_Campana = c.ID_Campana) as cantidad_personas')
                ])
                ->orderBy('c.ID_Campana', 'desc')
                ->get();

            $data = [];

            // Traduce el mes a español
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

            //map to data to format date to text
            $data = $campanas->map(function ($campana) use ($meses_es) {
                $no_campana = $meses_es[(int)$campana->Mes_Numero];
                return [
                    'ID_Campana' => $campana->ID_Campana,
                    'Fe_Creacion' => date('d/m/Y', strtotime($campana->Fe_Creacion)),
                    'No_Campana' => $no_campana,
                    'Fe_Inicio' => date('d/m/Y', strtotime($campana->Fe_Inicio)),
                    'Fe_Fin' => date('d/m/Y', strtotime($campana->Fe_Fin)),
                    'cantidad_personas' => $campana->cantidad_personas,
                   
                ];
            });
            $data = $data->toArray();

            return response()->json([
                'data' => $data,
                'success' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Error en index (getCampanasTabla): ' . $e->getMessage());
            return response()->json([
                'data' => [],
                'success' => false,
                'message' => 'Error al obtener las campañas: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * @OA\Delete(
     *     path="/campaigns/{id}",
     *     tags={"Campañas"},
     *     summary="Eliminar una campaña",
     *     description="Elimina una campaña de curso y sus días relacionados",
     *     operationId="destroyCampaign",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Campaña eliminada exitosamente"),
     *     @OA\Response(response=404, description="Campaña no encontrada")
     * )
     */
    public function destroy($id)
    {
        try {
            // Iniciar transacción para asegurar consistencia
            DB::beginTransaction();
            
            // Primero eliminar los días relacionados
            DB::table($this->table_campana_curso_dias)
                ->where('id_campana', $id)
                ->delete();
            
            // Luego eliminar la campaña
            $deleted = DB::table('campana_curso')
                ->where('ID_Campana', $id)
                ->delete();
            
            if ($deleted > 0) {
                DB::commit();
                return response()->json([
                    'success' => true, 
                    'message' => 'Campaña eliminada correctamente'
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'success' => false, 
                    'message' => 'No se encontró la campaña a eliminar'
                ], 404);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en destroy (eliminar campaña): ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Error al eliminar la campaña: ' . $e->getMessage()
            ], 500);
        }
    }
}
