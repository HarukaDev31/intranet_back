<?php

namespace App\Http\Controllers\Curso;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PedidoCurso;
use App\Models\Campana;
use App\Helpers\DateHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
class CursoController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            $search = $request->get('search', '');
            $campana = $request->get('campana', '');
            $fechaInicio = $request->get('fechaInicio', '');
            $fechaFin = $request->get('fechaFin', '');
            $estadoPago = $request->get('estado_pago', '');

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
                    DB::raw('0 AS tipo_curso'), // Por defecto virtual, se puede modificar según la lógica de negocio
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
                ->join('pais AS P', 'P.ID_Pais', '=', 'PC.ID_Pais')
                ->join('entidad AS CLI', 'CLI.ID_Entidad', '=', 'PC.ID_Entidad')
                ->join('tipo_documento_identidad AS TDI', 'TDI.ID_Tipo_Documento_Identidad', '=', 'CLI.ID_Tipo_Documento_Identidad')
                ->join('moneda AS M', 'M.ID_Moneda', '=', 'PC.ID_Moneda')
                ->join('usuario AS USR', 'USR.ID_Entidad', '=', 'CLI.ID_Entidad')
                ->join('campana_curso AS CAMP', 'CAMP.ID_Campana', '=', 'PC.ID_Campana')
                ->leftJoin('distrito AS DIST', 'DIST.ID_Distrito', '=', 'CLI.ID_Distrito')
                ->leftJoin('provincia AS PROV', 'PROV.ID_Provincia', '=', 'CLI.ID_Provincia')
                ->leftJoin('departamento AS DEP', 'DEP.ID_Departamento', '=', 'CLI.ID_Departamento')
                ->where('PC.ID_Empresa', $user->ID_Empresa);

            // Aplicar filtros
            if ($fechaInicio && $fechaFin) {
                $query->whereBetween('PC.Fe_Registro', [$fechaInicio, $fechaFin]);
            }

            if ($campana && $campana != '0') {
                $query->where('PC.ID_Campana', $campana);
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

            // Ordenar por fecha de registro descendente
            $query->orderBy('PC.Fe_Registro', 'desc');

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
                    'tipo_curso' => $curso->tipo_curso,
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
                    'puede_constancia' => ($tipoCurso == 1 && $fechaFin && strtotime($fechaHoy) > strtotime($fechaFin)),
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
                        'value' => $cursosProcessed->sum('Ss_Total'),
                        'label' => 'Importe Total'
                    ],
                    'total_pedidos' => [
                        'value' => $totalRecords,
                        'label' => 'Total Pedidos'
                    ]
                ],
                'filters' => [
                    'campanas' => $campanas
                ]
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
}
