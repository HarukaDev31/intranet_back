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
use App\Traits\CodeIgniterEncryption;

class CursoController extends Controller
{
    use CodeIgniterEncryption;
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
    public function actualizarDatosCliente(Request $request, $idEntidad)
    {
        $data = $request->only([
            'No_Entidad',
            'Nu_Tipo_Sexo',
            'Nu_Documento_Identidad',
            'Nu_Celular_Entidad',
            'Txt_Email_Entidad',
            'Fe_Nacimiento',
            'Nu_Como_Entero_Empresa'
        ]);
        $updated = DB::table('entidad')->where('ID_Entidad', $idEntidad)->update($data);
        if ($updated) {
            return response()->json(['status' => 'success', 'message' => 'Datos del cliente actualizados']);
        }
        return response()->json(['status' => 'warning', 'message' => 'No se modificó ningún dato']);
    }

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
    public function actualizarPedido(Request $request, $idPedido)
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
}
