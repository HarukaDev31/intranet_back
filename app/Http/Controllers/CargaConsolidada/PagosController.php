<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Pago;
use App\Models\CargaConsolidada\PagoConcept;
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

} 