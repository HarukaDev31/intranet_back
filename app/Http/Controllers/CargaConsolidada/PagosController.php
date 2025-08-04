<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Pago;
use App\Models\CargaConsolidada\PagoConcept;
use App\Models\PedidoCurso;
use App\Models\PedidoCursoPago;
use App\Models\PedidoCursoPagoConcept;
use App\Models\Entidad;
use App\Models\Pais;
use App\Models\Moneda;
use App\Models\Usuario;
use App\Models\Distrito;
use App\Models\Provincia;
use App\Models\Departamento;
use App\Models\TipoDocumentoIdentidad;
use App\Models\Campana;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PagosController extends Controller
{
    /**
     * Obtener consolidado de pagos
     */
    public function getConsolidadoPagos(Request $request)
    {
        try {
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);

            $query = Cotizacion::with(['contenedor', 'pagos.concepto'])
                ->select([
                    'contenedor_consolidado_cotizacion.*',
                    'carga_consolidada_contenedor.id as id_consolidado',
                    'carga_consolidada_contenedor.carga as carga',
                    DB::raw('(
                        SELECT COUNT(*)
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos as ccp
                        JOIN cotizacion_coordinacion_pagos_concept as ccpc ON ccp.id_concept = ccpc.id
                        WHERE ccp.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccpc.name = "LOGISTICA" OR ccpc.name = "IMPUESTOS")
                    ) AS total_pagos'),
                    DB::raw('(
                        SELECT IFNULL(SUM(ccp.monto), 0)
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos as ccp
                        JOIN cotizacion_coordinacion_pagos_concept as ccpc ON ccp.id_concept = ccpc.id
                        WHERE ccp.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccpc.name = "LOGISTICA" OR ccpc.name = "IMPUESTOS")
                    ) AS total_pagos_monto'),
                    DB::raw('(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                "id_pago", ccp2.id,
                                "monto", ccp2.monto,
                                "concepto", ccpc2.name,
                                "status", ccp2.status,
                                "payment_date", ccp2.payment_date
                            )
                        ) FROM contenedor_consolidado_cotizacion_coordinacion_pagos as ccp2
                        LEFT JOIN cotizacion_coordinacion_pagos_concept as ccpc2 ON ccp2.id_concept = ccpc2.id
                        WHERE ccp2.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccp2.id_concept = ' . PagoConcept::CONCEPT_PAGO_LOGISTICA . '
                        OR ccp2.id_concept = ' . PagoConcept::CONCEPT_PAGO_IMPUESTOS . ')
                    ) as pagos_details')
                ])
                ->join('carga_consolidada_contenedor', 'carga_consolidada_contenedor.id', '=', 'contenedor_consolidado_cotizacion.id_contenedor');

            // Filtros de fecha
            if ($request->filled('Filtro_Fe_Inicio')) {
                $query->where('contenedor_consolidado_cotizacion.fecha', '>=', $request->Filtro_Fe_Inicio);
            }

            if ($request->filled('Filtro_Fe_Fin')) {
                $query->where('contenedor_consolidado_cotizacion.fecha', '<=', $request->Filtro_Fe_Fin);
            }

            // Filtros opcionales adicionales
            if ($request->filled('estado')) {
                $query->where('contenedor_consolidado_cotizacion.estado', $request->estado);
            }

            // Filtro por campaña
            if ($request->filled('campana') && $request->campana != '0') {
                $query->where('carga_consolidada_contenedor.carga', $request->campana);
            }

            $query->where('contenedor_consolidado_cotizacion.estado_cotizador', 'CONFIRMADO');

            // Filtros complejos
            $query->where(function($q) {
                // Opción 1: Tiene pagos
                $q->whereExists(function($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('contenedor_consolidado_cotizacion_coordinacion_pagos')
                        ->whereRaw('contenedor_consolidado_cotizacion_coordinacion_pagos.id_cotizacion = contenedor_consolidado_cotizacion.id')
                        ->whereIn('id_concept', [PagoConcept::CONCEPT_PAGO_LOGISTICA, PagoConcept::CONCEPT_PAGO_IMPUESTOS]);
                });

                // Opción 2: estado_cliente no es null Y contenedor completado
                $q->orWhere(function($subQ) {
                    $subQ->whereNotNull('contenedor_consolidado_cotizacion.estado_cliente')
                        ->where('carga_consolidada_contenedor.estado_china', 'COMPLETADO');
                });
            });

            // Obtener datos paginados
            $cotizaciones = $query->paginate($perPage, ['*'], 'page', $page);

            $data = [];
            $index = ($page - 1) * $perPage + 1;

            foreach ($cotizaciones->items() as $cotizacion) {
                $aPagar = ($cotizacion->logistica_final + $cotizacion->impuestos_final) == 0 ? 
                    $cotizacion->monto : ($cotizacion->logistica_final + $cotizacion->impuestos_final);

                // Determinar estado de pago
                $estadoPago = $this->determinarEstadoPago($cotizacion->total_pagos, $cotizacion->total_pagos_monto, $aPagar);

                // Filtro por estado de pago
                if ($request->filled('estado_pago') && $request->estado_pago != '0') {
                    if ($estadoPago !== $request->estado_pago) {
                        continue;
                    }
                }

                $pagosDetalle = $this->procesarPagosDetalle($cotizacion->pagos_details);
                $estadosDisponibles = $this->getEstadosDisponibles();

                $data[] = [
                    'id' => $cotizacion->id,
                    'index' => $index,
                    'fecha' => Carbon::parse($cotizacion->fecha)->format('d-m-Y'),
                    'nombre' => $cotizacion->nombre,
                    'documento' => $cotizacion->documento,
                    'telefono' => $cotizacion->telefono,
                    'tipo' => "Consolidado",
                    'carga' => $cotizacion->carga,
                    'estado_pago' => $estadoPago,
                    'estados_disponibles' => $estadosDisponibles,
                    'monto_a_pagar' => (($aPagar) == 0 ? $cotizacion->monto : $aPagar),
                    'monto_a_pagar_formateado' => number_format((($aPagar) == 0 ? $cotizacion->monto : $aPagar), 2, '.', ''),
                    'total_pagado' => $cotizacion->total_pagos_monto,
                    'total_pagado_formateado' => number_format($cotizacion->total_pagos_monto, 2, '.', ''),
                    'pagos_detalle' => $pagosDetalle,
                    'note_administracion' => $cotizacion->note_administracion
                ];

                $index++;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $cotizaciones->currentPage(),
                    'last_page' => $cotizaciones->lastPage(),
                    'per_page' => $cotizaciones->perPage(),
                    'total' => $cotizaciones->total(),
                    'from' => $cotizaciones->firstItem(),
                    'to' => $cotizaciones->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('PagosController getConsolidadoPagos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener consolidado de pagos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determinar estado de pago
     */
    private function determinarEstadoPago($totalPagos, $totalPagosMonto, $aPagar)
    {
        if ($totalPagos == 0) {
            return 'PENDIENTE';
        } else if ($totalPagosMonto < $aPagar) {
            return 'ADELANTO';
        } else if ($totalPagosMonto == $aPagar) {
            return 'PAGADO';
        } else if ($totalPagosMonto > $aPagar) {
            return 'SOBREPAGO';
        }
        return 'PENDIENTE';
    }



    /**
     * Procesar detalles de pagos
     */
    private function procesarPagosDetalle($pagosDetails)
    {
        $pagos = json_decode($pagosDetails, true);
        $pagosProcesados = [];
        
        if ($pagos) {
            foreach ($pagos as $pago) {
                $pagosProcesados[] = [
                    'id' => $pago['id_pago'],
                    'monto' => $pago['monto'],
                    'monto_formateado' => number_format($pago['monto'], 2, '.', ''),
                    'concepto' => $pago['concepto'],
                    'status' => $pago['status'],
                    'payment_date' => $pago['payment_date']
                ];
            }
        }
        
        return $pagosProcesados;
    }

    /**
     * Obtener estados disponibles
     */
    private function getEstadosDisponibles()
    {
        return [
            [
                'value' => 'PENDIENTE',
                'label' => 'Pendiente'
            ],
            [
                'value' => 'ADELANTO',
                'label' => 'Adelanto'
            ],
            [
                'value' => 'PAGADO',
                'label' => 'Pagado'
            ],
            [
                'value' => 'SOBREPAGO',
                'label' => 'Sobrepago'
            ]
        ];
    }

    /**
     * Obtener pagos de coordinación
     */
    private function getPagosCoordination($idCotizacion)
    {
        try {
            $pagos = Pago::with('concepto')
                ->where('id_cotizacion', $idCotizacion)
                ->whereIn('id_concept', [PagoConcept::CONCEPT_PAGO_LOGISTICA, PagoConcept::CONCEPT_PAGO_IMPUESTOS])
                ->orderBy('payment_date', 'DESC')
                ->get();

            return $pagos;
        } catch (\Exception $e) {
            Log::error('Error en getPagosCoordination: ' . $e->getMessage());
            return [
                'status' => "error",
                'message' => 'Error al obtener los pagos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener detalles de pagos consolidados
     */
    public function getDetailsPagosConsolidado($idCotizacion)
    {
        try {
            $details = $this->getPagosCoordination($idCotizacion);
            
            $cotizacion = Cotizacion::select('note_administracion', 'cotizacion_file_url', 'cotizacion_final_url', 'monto', 'logistica_final', 'impuestos_final')
                ->where('id', $idCotizacion)
                ->first();

            // Calcular monto a pagar usando la misma lógica del método principal
            $aPagar = ($cotizacion->logistica_final + $cotizacion->impuestos_final) == 0 ? 
                $cotizacion->monto : ($cotizacion->logistica_final + $cotizacion->impuestos_final);

            // Calcular total pagado sumando todos los pagos
            $totalPagado = $details->sum('monto');

            return response()->json([
                'success' => true,
                'data' => $details,
                'nota' => $cotizacion->note_administracion ?? '',
                'cotizacion_inicial_url' => $cotizacion->cotizacion_file_url ?? '',
                'cotizacion_final_url' => $cotizacion->cotizacion_final_url ?? '',
                'total_a_pagar' => $aPagar,
                'total_a_pagar_formateado' => number_format($aPagar, 2, '.', ''),
                'total_pagado' => $totalPagado,
                'total_pagado_formateado' => number_format($totalPagado, 2, '.', '')
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getDetailsPagosConsolidado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los detalles de los pagos consolidados: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener pagos de cursos
     */
    public function getCursosPagos(Request $request)
    {
        try {
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);

            $query = PedidoCurso::with(['entidad', 'pais', 'moneda', 'usuario'])
                ->select([
                    'pedido_curso.*',
                    'entidad.Fe_Nacimiento',
                    'entidad.Nu_Como_Entero_Empresa',
                    'entidad.No_Otros_Como_Entero_Empresa',
                    'distrito.No_Distrito',
                    'provincia.No_Provincia',
                    'departamento.No_Departamento',
                    'tipo_documento_identidad.No_Tipo_Documento_Identidad_Breve',
                    'pais.No_Pais',
                    'entidad.Nu_Tipo_Sexo',
                    'entidad.No_Entidad',
                    'entidad.Nu_Documento_Identidad',
                    'entidad.Nu_Celular_Entidad',
                    'entidad.Txt_Email_Entidad',
                    'entidad.Nu_Edad',
                    'moneda.No_Signo',
                    'usuario.ID_Usuario',
                    'usuario.No_Usuario',
                    'usuario.No_Password',
                    DB::raw('(
                        SELECT COUNT(*)
                        FROM pedido_curso_pagos as cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = pedido_curso.ID_Pedido_Curso
                        AND (ccp.name = "ADELANTO")
                    ) AS pagos_count'),
                    DB::raw('(
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM pedido_curso_pagos as cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = pedido_curso.ID_Pedido_Curso
                        AND (ccp.name = "ADELANTO")
                    ) AS total_pagos'),
                    DB::raw('(SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            "id_pago", cccp2.id,
                            "monto", cccp2.monto,
                            "status", cccp2.status
                        )   
                    ) FROM pedido_curso_pagos as cccp2
                    WHERE cccp2.id_pedido_curso = pedido_curso.ID_Pedido_Curso 
                    AND cccp2.id_concept = ' . PedidoCursoPagoConcept::CONCEPT_PAGO_ADELANTO_CURSO . '   
                    ) as pagos_details')
                ])
                ->join('pais', 'pais.ID_Pais', '=', 'pedido_curso.ID_Pais')
                ->join('entidad', 'entidad.ID_Entidad', '=', 'pedido_curso.ID_Entidad')
                ->join('tipo_documento_identidad', 'tipo_documento_identidad.ID_Tipo_Documento_Identidad', '=', 'entidad.ID_Tipo_Documento_Identidad')
                ->join('moneda', 'moneda.ID_Moneda', '=', 'pedido_curso.ID_Moneda')
                ->join('usuario', 'usuario.ID_Entidad', '=', 'entidad.ID_Entidad')
                ->leftJoin('distrito', 'distrito.ID_Distrito', '=', 'entidad.ID_Distrito')
                ->leftJoin('provincia', 'provincia.ID_Provincia', '=', 'entidad.ID_Provincia')
                ->leftJoin('departamento', 'departamento.ID_Departamento', '=', 'entidad.ID_Departamento');

            // Filtro por empresa del usuario autenticado
            $query->where('pedido_curso.ID_Empresa', auth()->user()->ID_Empresa);

            // Filtro para cursos que tienen pagos de adelanto
            $query->whereExists(function($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('pedido_curso_pagos')
                    ->whereRaw('pedido_curso_pagos.id_pedido_curso = pedido_curso.ID_Pedido_Curso')
                    ->where('id_concept', PedidoCursoPagoConcept::CONCEPT_PAGO_ADELANTO_CURSO);
            });

            // Filtros de fecha
            if ($request->filled('Filtro_Fe_Inicio')) {
                $query->where('pedido_curso.Fe_Emision', '>=', $request->Filtro_Fe_Inicio);
            }

            if ($request->filled('Filtro_Fe_Fin')) {
                $query->where('pedido_curso.Fe_Emision', '<=', $request->Filtro_Fe_Fin);
            }

            // Filtro por campaña
            if ($request->filled('campana') && $request->campana != '0') {
                $query->where('pedido_curso.ID_Campana', $request->campana);
            }

            // Obtener datos paginados
            $cursos = $query->paginate($perPage, ['*'], 'page', $page);

            $data = [];
            $index = ($page - 1) * $perPage + 1;

            foreach ($cursos->items() as $curso) {
                $aPagar = ($curso->logistica_final + $curso->impuestos_final) == 0 ? 
                    $curso->Ss_Total : ($curso->logistica_final + $curso->impuestos_final);

                // Determinar estado de pago
                $estadoPago = $this->determinarEstadoPago($curso->pagos_count, $curso->total_pagos, $aPagar);

                // Filtro por estado de pago
                if ($request->filled('estado_pago') && $request->estado_pago != '0') {
                    if ($estadoPago !== $request->estado_pago) {
                        continue;
                    }
                }

                $pagosDetalle = $this->procesarPagosDetalleCurso($curso->pagos_details);

                $data[] = [
                    'id' => $curso->ID_Pedido_Curso,
                    'index' => $index,
                    'fecha_registro' => Carbon::parse($curso->Fe_Registro)->format('d-m-Y'),
                    'nombre' => $curso->No_Entidad,
                    'telefono' => $curso->Nu_Celular_Entidad,
                    'tipo' => "Curso",
                    'campana' => $this->obtenerNombreCampana($curso->ID_Campana),
                    'estado_pago' => $estadoPago,
                    'estados_disponibles' => $this->getEstadosDisponibles(),
                    'monto_a_pagar' => $aPagar,
                    'monto_a_pagar_formateado' => number_format($aPagar, 2, '.', ''),
                    'total_pagado' => $curso->total_pagos,
                    'total_pagado_formateado' => number_format($curso->total_pagos, 2, '.', ''),
                    'pagos_detalle' => $pagosDetalle,
                    'note_administracion' => $curso->note_administracion
                ];

                $index++;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $cursos->currentPage(),
                    'last_page' => $cursos->lastPage(),
                    'per_page' => $cursos->perPage(),
                    'total' => $cursos->total(),
                    'from' => $cursos->firstItem(),
                    'to' => $cursos->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('PagosController getCursosPagos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pagos de cursos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesar detalles de pagos de curso
     */
    private function procesarPagosDetalleCurso($pagosDetails)
    {
        $pagos = json_decode($pagosDetails, true);
        $pagosProcesados = [];
        
        if ($pagos) {
            foreach ($pagos as $pago) {
                $pagosProcesados[] = [
                    'id' => $pago['id_pago'],
                    'monto' => $pago['monto'],
                    'monto_formateado' => number_format($pago['monto'], 2, '.', ''),
                    'status' => $pago['status']
                ];
            }
        }
        
        return $pagosProcesados;
    }

    /**
     * Obtener nombre de campaña
     */
    private function obtenerNombreCampana($idCampana)
    {
        if (!$idCampana) {
            return "";
        }
        
        $campana = Campana::find($idCampana);
        return $campana ? $campana->nombre_campana : "Campaña " . $idCampana;
    }

    /**
     * Obtener detalles de pagos de curso
     */
    public function getDetailsPagosCurso($idPedidoCurso)
    {
        try {
            $pedidoCurso = PedidoCurso::findOrFail($idPedidoCurso);
            
            $pagos = PedidoCursoPago::with('concepto')
                ->where('id_pedido_curso', $idPedidoCurso)
                ->where('id_concept', PedidoCursoPagoConcept::CONCEPT_PAGO_ADELANTO_CURSO)
                ->orderBy('payment_date', 'DESC')
                ->get();

            // Calcular monto a pagar usando la misma lógica del método principal
            $aPagar = ($pedidoCurso->logistica_final + $pedidoCurso->impuestos_final) == 0 ? 
                $pedidoCurso->Ss_Total : ($pedidoCurso->logistica_final + $pedidoCurso->impuestos_final);

            // Calcular total pagado sumando todos los pagos
            $totalPagado = $pagos->sum('monto');

            return response()->json([
                'success' => true,
                'data' => $pagos,
                'nota' => $pedidoCurso->note_administracion ?? '',
                'total_a_pagar' => $aPagar,
                'total_a_pagar_formateado' => number_format($aPagar, 2, '.', ''),
                'total_pagado' => $totalPagado,
                'total_pagado_formateado' => number_format($totalPagado, 2, '.', '')
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getDetailsPagosCurso: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los detalles de los pagos del curso: ' . $e->getMessage()
            ], 500);
        }
    }

} 