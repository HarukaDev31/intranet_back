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
            $search = $request->get('search', '');

            $perPage = $request->get('limit', 100);
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
                                "payment_date", ccp2.payment_date,
                                "voucher_url", ccp2.voucher_url,
                                "banco", ccp2.banco
                            )
                        ) FROM contenedor_consolidado_cotizacion_coordinacion_pagos as ccp2
                        LEFT JOIN cotizacion_coordinacion_pagos_concept as ccpc2 ON ccp2.id_concept = ccpc2.id
                        WHERE ccp2.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccp2.id_concept = ' . PagoConcept::CONCEPT_PAGO_LOGISTICA . '
                        OR ccp2.id_concept = ' . PagoConcept::CONCEPT_PAGO_IMPUESTOS . ')
                    ) as pagos_details')
                ])
                ->join('carga_consolidada_contenedor', 'carga_consolidada_contenedor.id', '=', 'contenedor_consolidado_cotizacion.id_contenedor')
                ->whereNull('contenedor_consolidado_cotizacion.id_cliente_importacion')->orderBy('contenedor_consolidado_cotizacion.id', 'asc');

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

            // Aplicar búsqueda (nombre, documento, teléfono, carga)
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('contenedor_consolidado_cotizacion.nombre', 'like', "%{$search}%")
                      ->orWhere('contenedor_consolidado_cotizacion.documento', 'like', "%{$search}%")
                      ->orWhere('contenedor_consolidado_cotizacion.telefono', 'like', "%{$search}%")
                      ->orWhere('carga_consolidada_contenedor.carga', 'like', "%{$search}%")
                      ->orWhereExists(function ($subQuery) use ($search) {
                          // Buscar adelantos por monto o referencia
                          $subQuery->select(DB::raw(1))
                              ->from('contenedor_consolidado_cotizacion_coordinacion_pagos')
                              ->whereRaw('contenedor_consolidado_cotizacion_coordinacion_pagos.id_cotizacion = contenedor_consolidado_cotizacion.id')
                              ->where(function ($innerQuery) use ($search) {
                                  $innerQuery->where('monto', 'like', "%{$search}%")
                                             ->orWhere('banco', 'like', "%{$search}%")
                                             ->orWhere('voucher_url', 'like', "%{$search}%");
                              });
                      });
                });
            }

            // Filtros complejos
            $query->where(function ($q) {
                // Opción 1: Tiene pagos
                $q->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('contenedor_consolidado_cotizacion_coordinacion_pagos')
                        ->whereRaw('contenedor_consolidado_cotizacion_coordinacion_pagos.id_cotizacion = contenedor_consolidado_cotizacion.id')
                        ->whereIn('id_concept', [PagoConcept::CONCEPT_PAGO_LOGISTICA, PagoConcept::CONCEPT_PAGO_IMPUESTOS]);
                });

                // Opción 2: estado_cliente no es null Y contenedor completado
                $q->orWhere(function ($subQ) {
                    $subQ->whereNotNull('contenedor_consolidado_cotizacion.estado_cliente')
                        ->where('carga_consolidada_contenedor.estado_china', 'COMPLETADO');
                });
            });

            // Aplicar filtro de estado_pago antes de la paginación
            if ($request->filled('estado_pago') && $request->estado_pago != '0') {
                $estadoPagoFiltro = $request->estado_pago;
                
                if ($estadoPagoFiltro === 'PENDIENTE') {
                    // Para PENDIENTE: no tiene pagos
                    $query->whereNotExists(function ($noPagosQuery) {
                        $noPagosQuery->select(DB::raw(1))
                            ->from('contenedor_consolidado_cotizacion_coordinacion_pagos')
                            ->whereRaw('contenedor_consolidado_cotizacion_coordinacion_pagos.id_cotizacion = contenedor_consolidado_cotizacion.id')
                            ->whereIn('id_concept', [PagoConcept::CONCEPT_PAGO_LOGISTICA, PagoConcept::CONCEPT_PAGO_IMPUESTOS]);
                    });
                } elseif ($estadoPagoFiltro === 'PAGADO') {
                    // Para PAGADO: tiene pagos y monto pagado = monto a pagar
                    $query->whereExists(function ($pagosQuery) {
                        $pagosQuery->select(DB::raw(1))
                            ->from('contenedor_consolidado_cotizacion_coordinacion_pagos')
                            ->whereRaw('contenedor_consolidado_cotizacion_coordinacion_pagos.id_cotizacion = contenedor_consolidado_cotizacion.id')
                            ->whereIn('id_concept', [PagoConcept::CONCEPT_PAGO_LOGISTICA, PagoConcept::CONCEPT_PAGO_IMPUESTOS]);
                    });
                    
                    // Agregar condición para que el monto pagado sea = monto a pagar
                    $query->whereRaw('(
                        SELECT IFNULL(SUM(ccp.monto), 0)
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos as ccp
                        JOIN cotizacion_coordinacion_pagos_concept as ccpc ON ccp.id_concept = ccpc.id
                        WHERE ccp.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccpc.name = "LOGISTICA" OR ccpc.name = "IMPUESTOS")
                    ) = CASE 
                        WHEN (contenedor_consolidado_cotizacion.logistica_final + contenedor_consolidado_cotizacion.impuestos_final) = 0 
                        THEN contenedor_consolidado_cotizacion.monto 
                        ELSE (contenedor_consolidado_cotizacion.logistica_final + contenedor_consolidado_cotizacion.impuestos_final) 
                    END');
                } elseif ($estadoPagoFiltro === 'ADELANTO') {
                    // Para ADELANTO: tiene pagos pero monto pagado < monto a pagar
                    $query->whereExists(function ($pagosQuery) {
                        $pagosQuery->select(DB::raw(1))
                            ->from('contenedor_consolidado_cotizacion_coordinacion_pagos')
                            ->whereRaw('contenedor_consolidado_cotizacion_coordinacion_pagos.id_cotizacion = contenedor_consolidado_cotizacion.id')
                            ->whereIn('id_concept', [PagoConcept::CONCEPT_PAGO_LOGISTICA, PagoConcept::CONCEPT_PAGO_IMPUESTOS]);
                    });
                    
                    // Agregar condición para que el monto pagado sea < monto a pagar
                    $query->whereRaw('(
                        SELECT IFNULL(SUM(ccp.monto), 0)
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos as ccp
                        JOIN cotizacion_coordinacion_pagos_concept as ccpc ON ccp.id_concept = ccpc.id
                        WHERE ccp.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccpc.name = "LOGISTICA" OR ccpc.name = "IMPUESTOS")
                    ) < CASE 
                        WHEN (contenedor_consolidado_cotizacion.logistica_final + contenedor_consolidado_cotizacion.impuestos_final) = 0 
                        THEN contenedor_consolidado_cotizacion.monto 
                        ELSE (contenedor_consolidado_cotizacion.logistica_final + contenedor_consolidado_cotizacion.impuestos_final) 
                    END');
                } elseif ($estadoPagoFiltro === 'SOBREPAGO') {
                    // Para SOBREPAGO: tiene pagos y monto pagado > monto a pagar
                    $query->whereExists(function ($pagosQuery) {
                        $pagosQuery->select(DB::raw(1))
                            ->from('contenedor_consolidado_cotizacion_coordinacion_pagos')
                            ->whereRaw('contenedor_consolidado_cotizacion_coordinacion_pagos.id_cotizacion = contenedor_consolidado_cotizacion.id')
                            ->whereIn('id_concept', [PagoConcept::CONCEPT_PAGO_LOGISTICA, PagoConcept::CONCEPT_PAGO_IMPUESTOS]);
                    });
                    
                    // Agregar condición para que el monto pagado sea > monto a pagar
                    $query->whereRaw('(
                        SELECT IFNULL(SUM(ccp.monto), 0)
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos as ccp
                        JOIN cotizacion_coordinacion_pagos_concept as ccpc ON ccp.id_concept = ccpc.id
                        WHERE ccp.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccpc.name = "LOGISTICA" OR ccpc.name = "IMPUESTOS")
                    ) > CASE 
                        WHEN (contenedor_consolidado_cotizacion.logistica_final + contenedor_consolidado_cotizacion.impuestos_final) = 0 
                        THEN contenedor_consolidado_cotizacion.monto 
                        ELSE (contenedor_consolidado_cotizacion.logistica_final + contenedor_consolidado_cotizacion.impuestos_final) 
                    END');
                }
            }

            // Obtener datos paginados
            $cotizaciones = $query->paginate($perPage, ['*'], 'page', $page);

            $cargasDisponibles = $this->getCargasDisponibles();

            $data = [];
            $index = ($page - 1) * $perPage + 1;

            foreach ($cotizaciones->items() as $cotizacion) {
                $aPagar = ($cotizacion->logistica_final + $cotizacion->impuestos_final) == 0 ?
                    $cotizacion->monto : ($cotizacion->logistica_final + $cotizacion->impuestos_final);

                // Determinar estado de pago
                $estadoPago = $this->determinarEstadoPago($cotizacion->total_pagos, $cotizacion->total_pagos_monto, $aPagar);

                $pagosDetalle = $this->procesarPagosDetalle($cotizacion->pagos_details);

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
                ],
                'cargas_disponibles' => $cargasDisponibles,
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
        Log::info('totalPagos: ' . $totalPagos);
        Log::info('totalPagosMonto: ' . $totalPagosMonto);
        Log::info('aPagar: ' . $aPagar);
        Log::info('round($totalPagosMonto, 2): ' . round($totalPagosMonto, 2));
        Log::info('round($aPagar, 2): ' . round($aPagar, 2));
        if ($totalPagos == 0) {
            return 'PENDIENTE';
        } else if (round($totalPagosMonto, 2) < round($aPagar, 2)) {
            return 'ADELANTO';
        } else if (round($totalPagosMonto, 2) == round($aPagar, 2)) {
            return 'PAGADO';
        } else if (round($totalPagosMonto, 2) > round($aPagar, 2)) {
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
                    'id_pago' => $pago['id_pago'],
                    'monto' => $pago['monto'],
                    'monto_formateado' => number_format($pago['monto'], 2, '.', ''),
                    'concepto' => $pago['concepto'],
                    'status' => $pago['status'],
                    'payment_date' => $pago['payment_date'],
                    'banco' => $pago['banco'],
                    'voucher_url' => $this->generateImageUrl($pago['voucher_url'])
                    
                ];
            }
        }

        return $pagosProcesados;
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
        return $baseUrl .  '/' . $ruta;
    }
    /**
     * Obtener campañas disponibles
     */
    private function getCampanasDisponibles()
    {

        // Obtener campañas y devolver nombre + año generado desde Fe_Inicio (Mes Año)
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

        //crear consulta donde se obtengan todas las campañas disponibles de la consulta que no tenga Fe_Borrado
        return Campana::select('ID_Campana', 'Fe_Inicio', 'Fe_Fin')->whereNull('Fe_Borrado')->get()->map(function ($c) use ($meses_es) {
            $date = $c->Fe_Inicio ?? $c->Fe_Fin;
            if ($date) {
                try {
                    $dt = Carbon::parse($date);
                    $mes = (int) $dt->format('n');
                    $anio = $dt->format('Y');
                    $nombre = ($meses_es[$mes] ?? 'Mes') . ' ' . $anio;
                } catch (\Exception $e) {
                    $nombre = "Campaña " . $c->ID_Campana;
                    Log::error('Error al parsear fecha de campaña: ' . $e->getMessage());
                }
            } else {
                $nombre = "Campaña " . $c->ID_Campana;
            }

            return [
                'id' => $c->ID_Campana,
                'nombre' => $nombre
            ];
        })->values();
    }

    /**
     * Obtener cargas disponibles
     */
    private function getCargasDisponibles()
    {
        try {
            $cargas = DB::table('carga_consolidada_contenedor as cc')
                ->select('cc.carga')
                ->distinct()
                ->where('cc.empresa','!=','1')
                ->orderBy('cc.carga')
                ->get();
            //carga is string number order by number
            $cargas = $cargas->sortBy(function($carga) {
                return (int) $carga->carga;
            });
            return $cargas->values();
        } catch (\Exception $e) {
            Log::error('Error en getCargasDisponibles: ' . $e->getMessage());
            return collect();
        }
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
                ->orderBy('payment_date', 'asc')
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
    public function actualizarPagoCoordinacion(Request $request, $idPago)
    {
        $request->validate([
            'status' => 'required|string',
            'monto' => 'nullable|numeric',
            'payment_date' => 'nullable|date',
        ]);

        try {
            DB::beginTransaction();

            $pago = Pago::find($idPago);
            if (! $pago) {
                return response()->json(['success' => false, 'message' => 'Pago no encontrado'], 404);
            }

            // Guardar cambios permitidos
            $pago->status = $request->input('status');
            if ($request->filled('monto')) {
                $pago->monto = $request->input('monto');
            }
            if ($request->filled('payment_date')) {
                $pago->payment_date = $request->input('payment_date');
            }
            $pago->timestamps = false;
            $pago->save();

            // Recalcular totales para la cotización asociada (solo conceptos LOGISTICA / IMPUESTOS)
            $cotizacionId = $pago->id_cotizacion;
            $totales = Pago::where('id_cotizacion', $cotizacionId)
                ->whereIn('id_concept', [PagoConcept::CONCEPT_PAGO_LOGISTICA, PagoConcept::CONCEPT_PAGO_IMPUESTOS])
                ->selectRaw('COUNT(*) as pagos_count, IFNULL(SUM(monto),0) as total_pagos_monto')
                ->first();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pago actualizado correctamente',
                'pago' => $pago,
                'totales_cotizacion' => [
                    'pagos_count' => (int) ($totales->pagos_count ?? 0),
                    'total_pagos_monto' => (float) ($totales->total_pagos_monto ?? 0),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error actualizarPagoCoordinacion: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el pago: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Actualizar la nota de administración de una cotización
     */
    public function updateNotaConsolidado(Request $request, $idCotizacion)
    {
        $request->validate([
            'note_administracion' => 'nullable|string|max:255',
        ]);

        try {
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
            }

            $cotizacion->note_administracion = $request->input('note_administracion');
            $cotizacion->save();

            return response()->json(['success' => true, 'message' => 'Nota de administración actualizada correctamente']);
        } catch (\Exception $e) {
            Log::error("Error actualizarNotaAdministracion: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al actualizar la nota de administración: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener detalles de pagos consolidados
     */
    public function getDetailsPagosConsolidado($idCotizacion)
    {
        try {
            $details = $this->getPagosCoordination($idCotizacion);

            $cotizacion = Cotizacion::select('note_administracion', 'nombre', 'cotizacion_file_url', 'cotizacion_final_url', 'monto', 'logistica_final', 'impuestos_final')
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
                'cliente' => $cotizacion->nombre ?? '',
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
            $search = $request->get('search', '');
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
                            "status", cccp2.status,
                            "payment_date", cccp2.payment_date,
                            "voucher_url", cccp2.voucher_url,
                            "banco", cccp2.banco
                        )   
                    ) FROM pedido_curso_pagos as cccp2
                    WHERE cccp2.id_pedido_curso = pedido_curso.ID_Pedido_Curso 
                    AND cccp2.id_concept = ' . PedidoCursoPagoConcept::CONCEPT_PAGO_ADELANTO_CURSO . '   
                    ) as pagos_details')
                ])
                ->join('pais', 'pais.ID_Pais', '=', 'pedido_curso.ID_Pais')
                ->leftJoin('entidad', 'entidad.ID_Entidad', '=', 'pedido_curso.ID_Entidad')
                ->leftJoin('tipo_documento_identidad', 'tipo_documento_identidad.ID_Tipo_Documento_Identidad', '=', 'entidad.ID_Tipo_Documento_Identidad')
                ->leftJoin('moneda', 'moneda.ID_Moneda', '=', 'pedido_curso.ID_Moneda')
                ->leftJoin('usuario', 'usuario.ID_Entidad', '=', 'entidad.ID_Entidad')
                ->leftJoin('distrito', 'distrito.ID_Distrito', '=', 'entidad.ID_Distrito')
                ->leftJoin('provincia', 'provincia.ID_Provincia', '=', 'entidad.ID_Provincia')
                ->leftJoin('departamento', 'departamento.ID_Departamento', '=', 'entidad.ID_Departamento')
                ->orderBy('pedido_curso.ID_Pedido_Curso', 'asc');

            // Filtro por empresa del usuario autenticado

            // Filtro para cursos que tienen pagos de adelanto
            $query->whereExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('pedido_curso_pagos')
                    ->whereRaw('pedido_curso_pagos.id_pedido_curso = pedido_curso.ID_Pedido_Curso')
                    ->where('id_concept', PedidoCursoPagoConcept::CONCEPT_PAGO_ADELANTO_CURSO);
            });

            //filtrar por busqueda
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('entidad.No_Entidad', 'LIKE', "%{$search}%")
                      ->orWhere('entidad.Nu_Documento_Identidad', 'LIKE', "%{$search}%")
                      ->orWhere('entidad.Nu_Celular_Entidad', 'LIKE', "%{$search}%")
                      ->orWhere('pedido_curso.ID_Campana', 'LIKE', "%{$search}%")
                      ->orWhereExists(function ($subQuery) use ($search) {
                          // Buscar adelantos por monto o referencia
                          $subQuery->select(DB::raw(1))
                              ->from('pedido_curso_pagos')
                              ->whereRaw('pedido_curso_pagos.id_pedido_curso = pedido_curso.ID_Pedido_Curso')
                              ->where(function ($innerQuery) use ($search) {
                                  $innerQuery->where('monto', 'like', "%{$search}%")
                                             ->orWhere('banco', 'like', "%{$search}%")
                                             ->orWhere('voucher_url', 'like', "%{$search}%");
                              })
                              ->where('id_concept', PedidoCursoPagoConcept::CONCEPT_PAGO_ADELANTO_CURSO);
                      });
                });
            }

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

            // Aplicar filtro de estado_pago antes de la paginación
            if ($request->filled('estado_pago') && $request->estado_pago != '0') {
                $estadoPagoFiltro = $request->estado_pago;
                
                if ($estadoPagoFiltro === 'PENDIENTE') {
                    // Para PENDIENTE: no tiene pagos
                    $query->whereNotExists(function ($noPagosQuery) {
                        $noPagosQuery->select(DB::raw(1))
                            ->from('pedido_curso_pagos')
                            ->whereRaw('pedido_curso_pagos.id_pedido_curso = pedido_curso.ID_Pedido_Curso')
                            ->where('id_concept', PedidoCursoPagoConcept::CONCEPT_PAGO_ADELANTO_CURSO);
                    });
                } elseif ($estadoPagoFiltro === 'PAGADO') {
                    // Para PAGADO: tiene pagos y monto pagado = monto a pagar
                    $query->whereExists(function ($pagosQuery) {
                        $pagosQuery->select(DB::raw(1))
                            ->from('pedido_curso_pagos')
                            ->whereRaw('pedido_curso_pagos.id_pedido_curso = pedido_curso.ID_Pedido_Curso')
                            ->where('id_concept', PedidoCursoPagoConcept::CONCEPT_PAGO_ADELANTO_CURSO);
                    });
                    
                    // Agregar condición para que el monto pagado sea = monto a pagar
                    $query->whereRaw('(
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM pedido_curso_pagos as cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = pedido_curso.ID_Pedido_Curso
                        AND (ccp.name = "ADELANTO")
                    ) = CASE 
                        WHEN (pedido_curso.logistica_final + pedido_curso.impuestos_final) = 0 
                        THEN pedido_curso.Ss_Total 
                        ELSE (pedido_curso.logistica_final + pedido_curso.impuestos_final) 
                    END');
                } elseif ($estadoPagoFiltro === 'ADELANTO') {
                    // Para ADELANTO: tiene pagos pero monto pagado < monto a pagar
                    $query->whereExists(function ($pagosQuery) {
                        $pagosQuery->select(DB::raw(1))
                            ->from('pedido_curso_pagos')
                            ->whereRaw('pedido_curso_pagos.id_pedido_curso = pedido_curso.ID_Pedido_Curso')
                            ->where('id_concept', PedidoCursoPagoConcept::CONCEPT_PAGO_ADELANTO_CURSO);
                    });
                    
                    // Agregar condición para que el monto pagado sea < monto a pagar
                    $query->whereRaw('(
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM pedido_curso_pagos as cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = pedido_curso.ID_Pedido_Curso
                        AND (ccp.name = "ADELANTO")
                    ) < CASE 
                        WHEN (pedido_curso.logistica_final + pedido_curso.impuestos_final) = 0 
                        THEN pedido_curso.Ss_Total 
                        ELSE (pedido_curso.logistica_final + pedido_curso.impuestos_final) 
                    END');
                } elseif ($estadoPagoFiltro === 'SOBREPAGO') {
                    // Para SOBREPAGO: tiene pagos y monto pagado > monto a pagar
                    $query->whereExists(function ($pagosQuery) {
                        $pagosQuery->select(DB::raw(1))
                            ->from('pedido_curso_pagos')
                            ->whereRaw('pedido_curso_pagos.id_pedido_curso = pedido_curso.ID_Pedido_Curso')
                            ->where('id_concept', PedidoCursoPagoConcept::CONCEPT_PAGO_ADELANTO_CURSO);
                    });
                    
                    // Agregar condición para que el monto pagado sea > monto a pagar
                    $query->whereRaw('(
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM pedido_curso_pagos as cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = pedido_curso.ID_Pedido_Curso
                        AND (ccp.name = "ADELANTO")
                    ) > CASE 
                        WHEN (pedido_curso.logistica_final + pedido_curso.impuestos_final) = 0 
                        THEN pedido_curso.Ss_Total 
                        ELSE (pedido_curso.logistica_final + pedido_curso.impuestos_final) 
                    END');
                }
            }

            // Obtener datos paginados
            $cursos = $query->paginate($perPage, ['*'], 'page', $page);

            // Obtener campañas disponibles
            $campanasDisponibles = $this->getCampanasDisponibles();

            $data = [];
            $index = ($page - 1) * $perPage + 1;

            foreach ($cursos->items() as $curso) {
                $aPagar = ($curso->logistica_final + $curso->impuestos_final) == 0 ?
                    $curso->Ss_Total : ($curso->logistica_final + $curso->impuestos_final);

                // Determinar estado de pago
                $estadoPago = $this->determinarEstadoPago($curso->pagos_count, $curso->total_pagos, $aPagar);

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
                ],
                'campanas_disponibles' => $campanasDisponibles,
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
                    'status' => $pago['status'],
                    'payment_date' => $pago['payment_date'],
                    'banco' => $pago['banco'],
                    'voucher_url' => $this->generateImageUrl($pago['voucher_url'])
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
        if (! $idCampana) {
            return "";
        }

        $campana = Campana::select('Fe_Inicio', 'Fe_Fin')->find($idCampana);

        if (! $campana) {
            return "Campaña " . $idCampana;
        }

        $date = $campana->Fe_Inicio ?? $campana->Fe_Fin;
        if (! $date) {
            return "Campaña " . $idCampana;
        }

        try {
            $dt = Carbon::parse($date);
        } catch (\Exception $e) {
            return "Campaña " . $idCampana;
        }

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

        $mes = (int) $dt->format('n');
        $anio = $dt->format('Y');

        return ($meses_es[$mes] ?? 'Mes') . ' ' . $anio;
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
                ->orderBy('payment_date', 'asc')
                ->get();
            $nombre = $pedidoCurso->ID_Entidad;
            $entidad = Entidad::findOrFail($pedidoCurso->ID_Entidad);
            $nombre = $entidad->No_Entidad;
            // Calcular monto a pagar usando la misma lógica del método principal
            $aPagar = ($pedidoCurso->logistica_final + $pedidoCurso->impuestos_final) == 0 ?
                $pedidoCurso->Ss_Total : ($pedidoCurso->logistica_final + $pedidoCurso->impuestos_final);

            // Calcular total pagado sumando todos los pagos
            $totalPagado = $pagos->sum('monto');

            return response()->json([
                'success' => true,
                'data' => $pagos,
                'nombre' => $nombre,
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

    /**
     * Actualizar pagos de curso
     */
    public function updateStatusCurso(Request $request, $idPedidoCurso)
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        try {
            $pedidoCurso = PedidoCursoPago::findOrFail($idPedidoCurso);
            $pedidoCurso->status = $request->input('status');
            $pedidoCurso->timestamps = false;
            $pedidoCurso->save();

            return response()->json(['success' => true, 'message' => 'Estado del curso actualizado correctamente']);
        } catch (\Exception $e) {
            Log::error('Error en updateStatusCurso: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al actualizar el estado del curso: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar nota de curso
     */
    public function updateNotaCurso(Request $request, $idPedidoCurso)
    {
        $request->validate([
            'note_administracion' => 'nullable|string|max:255',
        ]);

        try {
            $pedido = PedidoCurso::findOrFail($idPedidoCurso);
            $pedido->note_administracion = $request->input('note_administracion');
            $pedido->timestamps = false;
            $pedido->save();

            return response()->json(['success' => true, 'message' => 'Nota de cotización actualizada correctamente']);
        } catch (\Exception $e) {
            Log::error('Error en updateNotaConsolidado: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al actualizar la nota de cotización: ' . $e->getMessage()], 500);
        }
    }
}
