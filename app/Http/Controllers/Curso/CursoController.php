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
use Illuminate\Support\Facades\Mail;
use App\Traits\CodeIgniterEncryption;
use App\Traits\MoodleRestProTrait;

class CursoController extends Controller
{
    use CodeIgniterEncryption, MoodleRestProTrait;
    public $table_pedido_curso_pagos = 'pedido_curso_pagos';
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
                MONTH(CC.Fe_Inicio) as mes_numero
            ")
            ->first();

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
        $data['mes_nombre'] = isset($data['mes_numero']) ? ($meses_es[(int)$data['mes_numero']] ?? '') : '';
        $data['password_moodle'] = $this->ciDecrypt($data['password_moodle']);
        return response()->json(['status' => 'success', 'data' => $data]);
    }



    /**
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
            DB::table('pedido_curso_pagos')->insert($data);
            return response()->json(['success' => true, 'message' => 'Pago guardado exitosamente', 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Error en saveClientePagosCurso: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al guardar el pago: ' . $e->getMessage()]);
        }
    }

    /**
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

    public function borrarCampana($id)
    {
        DB::table('campana_curso')->where('ID_Campana', $id)->update(['Fe_Borrado' => now()]);
        return response()->json(['status' => 'success', 'message' => 'Campaña eliminada correctamente']);
    }

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

    public function crearUsuarioCursosMoodle(Request $request)
    {
        try {
            $id = $request->input('id_usuario');
            $id_pedido_curso = $request->input('id_pedido');
            
            // Buscar usuario
            $response_usuario_bd = $this->getUsuario($id);
            if ($response_usuario_bd['status'] == 'success') {
                $result = $response_usuario_bd['result'][0];
                Log::error('result: ' . print_r($result, true));

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
                    $cleaned_password = 'TempPass' . rand(1000, 9999) . '!';
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
                    $cleaned_password = 'TempPass' . rand(1000, 9999) . '!';
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

                if ($response_usuario_moodle['status'] == 'success') {
                    // Buscar el usuario creado usando el nuevo username
                    $arrParams['criteria'][0]['key']   = 'username';
                    $arrParams['criteria'][0]['value'] = $username;
                    
                    // Set No_Usuario to $username
                    $this->setUsuarioModdle(
                        $username,
                        $this->encrypt($cleaned_password),
                        $id
                    );
                    
                    $response_usuario = $this->getUser($arrParams);

                    if ($response_usuario['status'] == 'success') {
                        $result_usuario = $response_usuario['response'];
                        $id_usuario = $result_usuario->id;

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

                            return response()->json([
                                'status' => 'success',
                                'message' => 'Usuario y curso creados exitosamente',
                                'data' => [
                                    'original_username' => $original_username,
                                    'moodle_username' => $username,
                                    'moodle_id' => $id_usuario,
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

                    return response()->json([
                        'status' => 'error',
                        'success' => true,
                        'message' => "El usuario ya existe en Moodle o hubo un error al crearlo: $error_message",
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

        // Si es muy corta o tiene caracteres problemáticos, generar nueva
        if (strlen($clean) < 8 || preg_match('/[^\w\d!@#%&*]/', $clean)) {
            return 'TempPass' . rand(1000, 9999) . '!';
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
                    $message .= "Puedes acceder a tu cuenta en el siguiente enlace: https://aulavirtualprobusiness.com/login/\n\n";
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

    public function actualizarDatosCliente(Request $request)
    {
        try {
            $id_entidad = $request->input('ID_Entidad');
            $data = [
                'No_Entidad'             => $request->input('No_Entidad'),
                'Nu_Documento_Identidad' => $request->input('Nu_Documento_Identidad'),
                'Nu_Tipo_Sexo'           => $request->input('Nu_Tipo_Sexo'),
                'Nu_Como_Entero_Empresa' => $request->input('Nu_Como_Entero_Empresa'),
                'Txt_Email_Entidad'      => $request->input('Txt_Email_Entidad'),
                'ID_Pais'                => $request->input('ID_Pais'),
                'Nu_Celular_Entidad'     => $request->input('Nu_Celular_Entidad'),
                'ID_Departamento'        => $request->input('ID_Departamento'),
                'Fe_Nacimiento'          => $request->input('Fe_Nacimiento'),
                'ID_Provincia'           => $request->input('ID_Provincia'),
                'ID_Distrito'            => $request->input('ID_Distrito'),
            ];
            
            Log::error('ID_Entidad: ' . print_r($id_entidad, true));
            Log::error('DATA: ' . print_r($data, true));
            
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
}
