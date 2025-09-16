<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardVentasController extends Controller
{
    /**
     * Obtiene la lista de contenedores para el filtro
     */
    public function getContenedoresFiltro(Request $request)
    {
        try {
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');

            $query = DB::table('carga_consolidada_contenedor as cont')
                ->select([
                    'cont.id',
                    'cont.carga',
                    'cont.fecha_zarpe',
                    DB::raw("CONCAT('Consolidado #',cont.carga) as label")
                ])
                ->where('cont.empresa', '!=', '1');
            //order by carga carga is number string
            $query->orderByRaw('CAST(carga AS UNSIGNED)');
           

            $contenedores = $query->get()->map(function($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->label,
                    'carga' => $item->carga,
                    'fecha_zarpe' => $item->fecha_zarpe ? Carbon::parse($item->fecha_zarpe)->format('d/m/Y') : null
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $contenedores
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getContenedoresFiltro: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener contenedores para filtro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene la lista de vendedores para el filtro
     */
    public function getVendedoresFiltro(Request $request)
    {
        try {
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');
            $idContenedor = $request->input('id_contenedor');

            $query = DB::table('usuario as u')
                ->select([
                    'u.ID_Usuario as id',
                    'u.No_Nombres_Apellidos as nombre',
                    DB::raw('COUNT(DISTINCT cc.id) as total_cotizaciones'),
                    DB::raw('COALESCE(SUM(cccp.cbm_total), 0) as volumen_total')
                ])
                ->join('contenedor_consolidado_cotizacion as cc', 'u.ID_Usuario', '=', 'cc.id_usuario')
                ->join('contenedor_consolidado_cotizacion_proveedores as cccp', 'cc.id', '=', 'cccp.id_cotizacion')
                ->join('carga_consolidada_contenedor as cont', 'cc.id_contenedor', '=', 'cont.id')
                ->groupBy('u.ID_Usuario', 'u.No_Nombres_Apellidos');

            if ($fechaInicio && $fechaFin) {
                $query->whereBetween('cont.fecha_zarpe', [$fechaInicio, $fechaFin]);
            }

            if ($idContenedor) {
                $query->where('cc.id_contenedor', $idContenedor);
            }

            $vendedores = $query->get()->map(function($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->nombre,
                    'total_cotizaciones' => $item->total_cotizaciones,
                    'volumen_total' => round($item->volumen_total, 2)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $vendedores
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getVendedoresFiltro: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener vendedores para filtro',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    private $table_contenedor_cotizacion = "contenedor_consolidado_cotizacion";
    private $table_contenedor_cotizacion_proveedores = "contenedor_consolidado_cotizacion_proveedores";
    private $table_contenedor = "carga_consolidada_contenedor";
    private $table_pagos = "contenedor_consolidado_cotizacion_coordinacion_pagos";
    private $table_pagos_concept = "cotizacion_coordinacion_pagos_concept";

    /**
     * Obtiene el resumen general de ventas por contenedor
     */
    public function getResumenVentas(Request $request)
    {
        try {
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');

            $query = DB::table($this->table_contenedor . ' as cont')
                ->select([
                    'cont.id as id_contenedor',
                    'cont.carga',
                    'cont.fecha_zarpe',
                    'u.ID_Usuario',
                    'u.No_Nombres_Apellidos as vendedor',
                    DB::raw('COUNT(DISTINCT cc.id) as total_clientes'),
                    // Volúmenes
                    DB::raw('COALESCE(SUM(IF(cc.estado_cotizador = "CONFIRMADO", cccp.cbm_total_china, 0)), 0) as volumen_china'),
                    
                    DB::raw('(
                        SELECT COALESCE(SUM(cccp2.cbm_total), 0)
                        FROM ' . $this->table_contenedor_cotizacion . ' cc2
                        JOIN ' . $this->table_contenedor_cotizacion_proveedores . ' cccp2 ON cc2.id = cccp2.id_cotizacion
                        WHERE cc2.id_contenedor = cont.id
                        AND cc2.id_usuario = u.ID_Usuario
                        AND cc2.estado_cotizador = "CONFIRMADO"
                    ) as volumen_total'),
                    
                    DB::raw('(
                        SELECT COALESCE(SUM(cccp2.cbm_total), 0)
                        FROM ' . $this->table_contenedor_cotizacion . ' cc2
                        JOIN ' . $this->table_contenedor_cotizacion_proveedores . ' cccp2 ON cc2.id = cccp2.id_cotizacion
                        WHERE cc2.id_contenedor = cont.id
                        AND cc2.id_usuario = u.ID_Usuario
                        AND cc2.estado_cotizador = "CONFIRMADO"
                    ) as volumen_vendido'),
                    
                    DB::raw('(
                        SELECT COALESCE(SUM(cccp2.cbm_total), 0)
                        FROM ' . $this->table_contenedor_cotizacion . ' cc2
                        JOIN ' . $this->table_contenedor_cotizacion_proveedores . ' cccp2 ON cc2.id = cccp2.id_cotizacion
                        WHERE cc2.id_contenedor = cont.id
                        AND cc2.id_usuario = u.ID_Usuario
                        AND (cc2.estado_cotizador != "CONFIRMADO" OR cc2.estado_cotizador IS NULL)
                    ) as volumen_pendiente'),
                    
                    // Totales monetarios
                    DB::raw('(
                        SELECT COALESCE(SUM(cc2.monto), 0)
                        FROM ' . $this->table_contenedor_cotizacion . ' cc2
                        WHERE cc2.id IN (
                            SELECT DISTINCT id_cotizacion
                            FROM ' . $this->table_contenedor_cotizacion_proveedores . ' cccp2
                            WHERE cccp2.id_contenedor = cont.id
                        )
                        AND cc2.id_usuario = u.ID_Usuario
                        AND cc2.estado_cotizador = "CONFIRMADO"
                        AND cc2.estado_cliente IS NOT NULL
                        AND cc2.id_cliente_importacion IS NULL
                    ) as total_logistica'),
                    
                    DB::raw('(
                        SELECT COALESCE(SUM(p2.monto), 0)
                        FROM ' . $this->table_pagos . ' p2
                        JOIN ' . $this->table_pagos_concept . ' pc2 ON p2.id_concept = pc2.id
                        JOIN ' . $this->table_contenedor_cotizacion . ' cc2 ON cc2.id_contenedor = p2.id_contenedor
                        WHERE p2.id_contenedor = cont.id
                        AND cc2.id_usuario = u.ID_Usuario
                        AND pc2.name = "LOGISTICA"
                    ) as total_logistica_pagado'),
                    
                    DB::raw('(
                        SELECT COALESCE(SUM(cc2.fob), 0)
                        FROM ' . $this->table_contenedor_cotizacion . ' cc2
                        WHERE cc2.id IN (
                            SELECT DISTINCT id_cotizacion
                            FROM ' . $this->table_contenedor_cotizacion_proveedores . ' cccp2
                            WHERE cccp2.id_contenedor = cont.id
                        )
                        AND cc2.id_usuario = u.ID_Usuario
                        AND cc2.estado_cotizador = "CONFIRMADO"
                        AND cc2.estado_cliente IS NOT NULL
                        AND cc2.id_cliente_importacion IS NULL
                    ) as total_fob'),
                    
                    DB::raw('(
                        SELECT COALESCE(SUM(cc2.impuestos), 0)
                        FROM ' . $this->table_contenedor_cotizacion . ' cc2
                        WHERE cc2.id_contenedor = cont.id
                        AND cc2.id_usuario = u.ID_Usuario
                        AND cc2.estado_cotizador = "CONFIRMADO"
                        AND cc2.estado_cliente IS NOT NULL
                        AND cc2.id_cliente_importacion IS NULL
                    ) as total_impuestos'),
                    DB::raw('(
                        SELECT 
                            (COALESCE(SUM(CASE WHEN cc2.estado_cotizador = "CONFIRMADO" THEN cccp2.cbm_total ELSE 0 END), 0) / 
                            NULLIF(COALESCE(SUM(cccp2.cbm_total), 0), 0)) * 100
                        FROM ' . $this->table_contenedor_cotizacion . ' cc2
                        JOIN ' . $this->table_contenedor_cotizacion_proveedores . ' cccp2 ON cc2.id = cccp2.id_cotizacion
                        WHERE cc2.id_contenedor = cont.id
                        AND cc2.id_usuario = u.ID_Usuario
                    ) as porcentaje_avance')
                ])
                ->leftJoin($this->table_contenedor_cotizacion . ' as cc', 'cont.id', '=', 'cc.id_contenedor')
                ->leftJoin($this->table_contenedor_cotizacion_proveedores . ' as cccp', 'cc.id', '=', 'cccp.id_cotizacion')
                ->leftJoin($this->table_pagos . ' as p', function($join) {
                    $join->on('p.id_contenedor', '=', 'cont.id')
                        ->on('p.id_cotizacion', '=', 'cc.id');
                })
                ->leftJoin($this->table_pagos_concept . ' as pc', 'p.id_concept', '=', 'pc.id')
                ->leftJoin('usuario as u', 'cc.id_usuario', '=', 'u.ID_Usuario')
                ->where('cont.empresa', '!=', '1')
                ->where('cc.estado_cotizador', 'CONFIRMADO')
                ->groupBy('cont.id', 'cont.carga', 'cont.fecha_zarpe', 'u.No_Nombres_Apellidos', 'u.ID_Usuario');
                
            // Aplicar filtros si existen
            if ($fechaInicio && $fechaFin) {
                $query->whereBetween('cont.fecha_zarpe', [$fechaInicio, $fechaFin]);
            }

            // Filtrar por vendedor
            if ($request->input('id_vendedor')) {
                $query->where('cc.id_usuario', $request->input('id_vendedor'));
            }

            // Filtrar por contenedor
            if ($request->input('id_contenedor')) {
                $query->where('cont.id', $request->input('id_contenedor'));
            }

            $data = $query->get()->map(function($item) {
                return [
                    'id_contenedor' => $item->id_contenedor,
                    'carga' => $item->carga,
                    'fecha_zarpe' => $item->fecha_zarpe ? Carbon::parse($item->fecha_zarpe)->format('d/m/Y') : null,
                    'vendedor' => $item->vendedor,
                    'total_clientes' => $item->total_clientes,
                    'volumenes' => [
                        'china' => round($item->volumen_china, 2),
                        'total' => round($item->volumen_total, 2),
                        'vendido' => round($item->volumen_vendido, 2),
                        'pendiente' => round($item->volumen_pendiente, 2)
                    ],
                    'totales' => [
                        'impuestos' => round($item->total_impuestos, 2),
                        'logistica' => round($item->total_logistica, 2),
                        'fob' => round($item->total_fob, 2)
                    ],
                    'metricas' => [
                        'porcentaje_avance' => round($item->porcentaje_avance, 2),
                        'meta_volumen' => 0, // Esto podría venir de una configuración
                        'meta_clientes' => 0 // Esto podría venir de una configuración
                    ]
                ];
            });

            // Calcular totales generales
            $totales = [
                'total_clientes' => $data->sum('total_clientes'),
                'volumenes' => [
                    'china' => round($data->sum(function($item) { return $item['volumenes']['china']; }), 2),
                    'total' => round($data->sum(function($item) { return $item['volumenes']['total']; }), 2),
                    'vendido' => round($data->sum(function($item) { return $item['volumenes']['vendido']; }), 2),
                    'pendiente' => round($data->sum(function($item) { return $item['volumenes']['pendiente']; }), 2)
                ],
                'totales' => [
                    'impuestos' => round($data->sum(function($item) { return $item['totales']['impuestos']; }), 2),
                    'logistica' => round($data->sum(function($item) { return $item['totales']['logistica']; }), 2),
                    'fob' => round($data->sum(function($item) { return $item['totales']['fob']; }), 2)
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'totales' => $totales
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getResumenVentas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el resumen de ventas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el detalle de ventas por vendedor
     */
    public function getVentasPorVendedor(Request $request)
    {
        try {
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');

            $query = DB::table('usuario as u')
                ->select([
                    'u.ID_Usuario as id_vendedor',
                    'u.No_Nombres_Apellidos as vendedor',
                    DB::raw('COUNT(DISTINCT cc.id) as total_cotizaciones'),
                    DB::raw('COUNT(DISTINCT CASE WHEN cc.estado_cotizador = "CONFIRMADO" THEN cc.id END) as cotizaciones_confirmadas'),
                    DB::raw('COALESCE(SUM(CASE WHEN cc.estado_cotizador = "CONFIRMADO" AND cc.estado_cliente IS NOT NULL AND cc.id_cliente_importacion IS NULL THEN cccp.cbm_total ELSE 0 END), 0) as volumen_total'),
                    
                    DB::raw('COALESCE(SUM(CASE WHEN cc.estado_cotizador = "CONFIRMADO" AND cc.estado_cliente IS NOT NULL AND cc.id_cliente_importacion IS NULL THEN cccp.cbm_total ELSE 0 END), 0) as volumen_vendido'),
                    
                    DB::raw('COALESCE(SUM(CASE WHEN cc.estado_cotizador = "CONFIRMADO" AND cc.estado_cliente IS NOT NULL AND cc.id_cliente_importacion IS NULL THEN cc.monto ELSE 0 END), 0) as total_logistica'),
                    
                    DB::raw('COALESCE(SUM(CASE WHEN cc.estado_cotizador = "CONFIRMADO" AND cc.estado_cliente IS NOT NULL AND cc.id_cliente_importacion IS NULL THEN cc.fob ELSE 0 END), 0) as total_fob'),
                    
                    DB::raw('COALESCE(SUM(CASE WHEN cc.estado_cotizador = "CONFIRMADO" AND cc.estado_cliente IS NOT NULL AND cc.id_cliente_importacion IS NULL THEN cc.impuestos ELSE 0 END), 0) as total_impuestos')
                ])
                ->leftJoin($this->table_contenedor_cotizacion . ' as cc', 'u.ID_Usuario', '=', 'cc.id_usuario')
                ->leftJoin($this->table_contenedor_cotizacion_proveedores . ' as cccp', 'cc.id', '=', 'cccp.id_cotizacion')
                ->leftJoin($this->table_contenedor . ' as cont', 'cc.id_contenedor', '=', 'cont.id')
                ->where('cont.empresa', '!=', '1');

            // Filtrar por vendedor
            if ($request->input('id_vendedor')) {
                $query->where('cc.id_usuario', $request->input('id_vendedor'));
            }

            // Filtrar por contenedor
            if ($request->input('id_contenedor')) {
                $query->where('cc.id_contenedor', $request->input('id_contenedor'));
            }
            if ($fechaInicio && $fechaFin) {
                $query->whereExists(function ($query) use ($fechaInicio, $fechaFin) {
                    $query->select(DB::raw(1))
                        ->from($this->table_contenedor . ' as cont')
                        ->join($this->table_contenedor_cotizacion . ' as cc2', 'cont.id', '=', 'cc2.id_contenedor')
                        ->whereBetween('cont.fecha_zarpe', [$fechaInicio, $fechaFin])
                        ->whereRaw('cc2.id_usuario = u.ID_Usuario');
                });
            }

            $query->groupBy('u.ID_Usuario', 'u.No_Nombres_Apellidos');

            $data = $query->get()->map(function($item) {
                $porcentaje_efectividad = $item->total_cotizaciones > 0 
                    ? ($item->cotizaciones_confirmadas / $item->total_cotizaciones) * 100 
                    : 0;

                return [
                    'id_vendedor' => $item->id_vendedor,
                    'vendedor' => $item->vendedor,
                    'metricas' => [
                        'total_cotizaciones' => $item->total_cotizaciones,
                        'cotizaciones_confirmadas' => $item->cotizaciones_confirmadas,
                        'porcentaje_efectividad' => round($porcentaje_efectividad, 2)
                    ],
                    'volumenes' => [
                        'total' => round($item->volumen_total, 2),
                        'vendido' => round($item->volumen_vendido, 2),
                        'pendiente' => round($item->volumen_total - $item->volumen_vendido, 2)
                    ],
                    'totales' => [
                        'logistica' => round($item->total_logistica, 2),
                        'fob' => round($item->total_fob, 2),
                        'impuestos' => round($item->total_impuestos, 2)
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getVentasPorVendedor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las ventas por vendedor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene la evolución de ventas por contenedor
     * Muestra el progreso de ventas, volúmenes y métricas por mes
     */
    public function getEvolucionContenedor(Request $request, $idContenedor)
    {
        try {
            // Obtener datos del contenedor
            $contenedor = DB::table($this->table_contenedor)
                ->select('id', 'carga', 'fecha_zarpe')
                ->where('id', $idContenedor)
                ->where('empresa', '!=', '1')
                ->first();

            if (!$contenedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contenedor no encontrado'
                ], 404);
            }

            // Obtener evolución por mes
            $evolucion = DB::table($this->table_contenedor_cotizacion . ' as cc')
                ->select([
                    DB::raw('DATE_FORMAT(cc.fecha, "%Y-%m") as mes'),
                    DB::raw('COUNT(DISTINCT cc.id) as total_cotizaciones'),
                    DB::raw('COUNT(DISTINCT CASE WHEN cc.estado_cotizador = "CONFIRMADO" THEN cc.id END) as cotizaciones_confirmadas'),
                    // Volúmenes
                    DB::raw('COALESCE(SUM(cccp.cbm_total_china), 0) as volumen_china'),
                    DB::raw('COALESCE(SUM(cccp.cbm_total), 0) as volumen_total'),
                    DB::raw('COALESCE(SUM(CASE WHEN cc.estado_cotizador = "CONFIRMADO" THEN cccp.cbm_total ELSE 0 END), 0) as volumen_vendido'),
                    DB::raw('COALESCE(SUM(CASE WHEN cc.estado_cotizador != "CONFIRMADO" OR cc.estado_cotizador IS NULL THEN cccp.cbm_total ELSE 0 END), 0) as volumen_pendiente'),
                    // Totales monetarios
                    DB::raw('COALESCE(SUM(cc.monto), 0) as total_logistica'),
                    DB::raw('COALESCE(SUM(cc.fob), 0) as total_fob'),
                    DB::raw('COALESCE(SUM(cc.impuestos), 0) as total_impuestos'),
                    // Métricas
                    DB::raw('COUNT(DISTINCT cc.id_usuario) as total_vendedores'),
                    DB::raw('(COALESCE(SUM(CASE WHEN cc.estado_cotizador = "CONFIRMADO" THEN cccp.cbm_total ELSE 0 END), 0) / 
                            NULLIF(COALESCE(SUM(cccp.cbm_total), 0), 0)) * 100 as porcentaje_avance')
                ])
                ->join($this->table_contenedor_cotizacion_proveedores . ' as cccp', 'cc.id', '=', 'cccp.id_cotizacion')
                ->where('cc.id_contenedor', $idContenedor)
                ->groupBy(DB::raw('DATE_FORMAT(cc.fecha, "%Y-%m")'))
                ->orderBy('mes', 'asc')
                ->get()
                ->map(function($item) {
                    return [
                        'mes' => $item->mes,
                        'cotizaciones' => [
                            'total' => $item->total_cotizaciones,
                            'confirmadas' => $item->cotizaciones_confirmadas,
                            'porcentaje_efectividad' => $item->total_cotizaciones > 0 
                                ? round(($item->cotizaciones_confirmadas / $item->total_cotizaciones) * 100, 2)
                                : 0
                        ],
                        'volumenes' => [
                            'china' => round($item->volumen_china, 2),
                            'total' => round($item->volumen_total, 2),
                            'vendido' => round($item->volumen_vendido, 2),
                            'pendiente' => round($item->volumen_pendiente, 2)
                        ],
                        'totales' => [
                            'logistica' => round($item->total_logistica, 2),
                            'fob' => round($item->total_fob, 2),
                            'impuestos' => round($item->total_impuestos, 2)
                        ],
                        'metricas' => [
                            'vendedores' => $item->total_vendedores,
                            'porcentaje_avance' => round($item->porcentaje_avance, 2)
                        ]
                    ];
                });

            // Obtener totales acumulados
            $totales = [
                'cotizaciones' => [
                    'total' => $evolucion->sum('cotizaciones.total'),
                    'confirmadas' => $evolucion->sum('cotizaciones.confirmadas'),
                    'porcentaje_efectividad' => $evolucion->sum('cotizaciones.total') > 0
                        ? round(($evolucion->sum('cotizaciones.confirmadas') / $evolucion->sum('cotizaciones.total')) * 100, 2)
                        : 0
                ],
                'volumenes' => [
                    'china' => round($evolucion->sum('volumenes.china'), 2),
                    'total' => round($evolucion->sum('volumenes.total'), 2),
                    'vendido' => round($evolucion->sum('volumenes.vendido'), 2),
                    'pendiente' => round($evolucion->sum('volumenes.pendiente'), 2)
                ],
                'totales' => [
                    'logistica' => round($evolucion->sum('totales.logistica'), 2),
                    'fob' => round($evolucion->sum('totales.fob'), 2),
                    'impuestos' => round($evolucion->sum('totales.impuestos'), 2)
                ],
                'metricas' => [
                    'vendedores' => $evolucion->max('metricas.vendedores'),
                    'porcentaje_avance' => round($evolucion->avg('metricas.porcentaje_avance'), 2)
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'contenedor' => [
                        'id' => $contenedor->id,
                        'carga' => $contenedor->carga,
                        'fecha_zarpe' => $contenedor->fecha_zarpe ? Carbon::parse($contenedor->fecha_zarpe)->format('d/m/Y') : null
                    ],
                    'evolucion' => $evolucion,
                    'totales' => $totales
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getEvolucionContenedor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la evolución del contenedor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene la evolución total de volúmenes para todos los contenedores
     * Útil para gráficas de tendencia de volumen vendido, china y pendiente
     */
    public function getEvolucionTotal(Request $request)
    {
        try {
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');

            $query = DB::table($this->table_contenedor . ' as cont')
                ->select([
                    'cont.id as id_contenedor',
                    'cont.carga',
                    'cont.fecha_zarpe',
                    // Volúmenes
                    DB::raw('(
                        SELECT COALESCE(SUM(cccp2.cbm_total_china), 0)
                        FROM ' . $this->table_contenedor_cotizacion . ' cc2
                        JOIN ' . $this->table_contenedor_cotizacion_proveedores . ' cccp2 ON cc2.id = cccp2.id_cotizacion
                        WHERE cc2.id_contenedor = cont.id
                        AND cc2.estado_cotizador = "CONFIRMADO"
                    ) as volumen_china'),
                    
                    DB::raw('(
                        SELECT COALESCE(SUM(cccp2.cbm_total), 0)
                        FROM ' . $this->table_contenedor_cotizacion . ' cc2
                        JOIN ' . $this->table_contenedor_cotizacion_proveedores . ' cccp2 ON cc2.id = cccp2.id_cotizacion
                        WHERE cc2.id_contenedor = cont.id
                        AND cc2.estado_cotizador = "CONFIRMADO"
                    ) as volumen_vendido'),
                    
                    DB::raw('(
                        SELECT COALESCE(SUM(cccp2.cbm_total), 0)
                        FROM ' . $this->table_contenedor_cotizacion . ' cc2
                        JOIN ' . $this->table_contenedor_cotizacion_proveedores . ' cccp2 ON cc2.id = cccp2.id_cotizacion
                        WHERE cc2.id_contenedor = cont.id
                        AND (cc2.estado_cotizador != "CONFIRMADO" OR cc2.estado_cotizador IS NULL)
                    ) as volumen_pendiente')
                ])
                ->where('cont.empresa', '!=', '1');

            // Filtrar por vendedor
            if ($request->input('id_vendedor')) {
                $query->whereExists(function ($query) use ($request) {
                    $query->select(DB::raw(1))
                        ->from($this->table_contenedor_cotizacion . ' as cc')
                        ->whereRaw('cc.id_contenedor = cont.id')
                        ->where('cc.id_usuario', $request->input('id_vendedor'));
                });
            }

            // Filtrar por contenedor
            if ($request->input('id_contenedor')) {
                $query->where('cont.id', $request->input('id_contenedor'));
            }

            //order by carga carga is number string
            $query->orderByRaw('CAST(carga AS UNSIGNED)');
            // Aplicar filtros de fecha si existen
            if ($fechaInicio && $fechaFin) {
                $query->whereBetween('cont.fecha_zarpe', [$fechaInicio, $fechaFin]);
            }

            $data = $query->orderBy('cont.fecha_zarpe', 'asc')
                ->get()
                ->map(function($item) {
                    return [
                        'contenedor' => [
                            'id' => $item->id_contenedor,
                            'carga' => $item->carga,
                            'fecha' => $item->fecha_zarpe ? Carbon::parse($item->fecha_zarpe)->format('d/m/Y') : null
                        ],
                        'volumenes' => [
                            'china' => round($item->volumen_china, 2),
                            'vendido' => round($item->volumen_vendido, 2),
                            'pendiente' => round($item->volumen_pendiente, 2),
                            'total' => round($item->volumen_vendido + $item->volumen_pendiente, 2)
                        ]
                    ];
                });

            // Calcular totales
            $totales = [
                'volumenes' => [
                    'china' => round($data->sum('volumenes.china'), 2),
                    'vendido' => round($data->sum('volumenes.vendido'), 2),
                    'pendiente' => round($data->sum('volumenes.pendiente'), 2),
                    'total' => round($data->sum('volumenes.total'), 2)
                ],
                'porcentajes' => [
                    'vendido' => $data->sum('volumenes.total') > 0 
                        ? round(($data->sum('volumenes.vendido') / $data->sum('volumenes.total')) * 100, 2)
                        : 0,
                    'pendiente' => $data->sum('volumenes.total') > 0 
                        ? round(($data->sum('volumenes.pendiente') / $data->sum('volumenes.total')) * 100, 2)
                        : 0
                ]
            ];

            // Calcular promedios por contenedor
            $promedios = [
                'volumenes' => [
                    'china' => round($data->avg('volumenes.china'), 2),
                    'vendido' => round($data->avg('volumenes.vendido'), 2),
                    'pendiente' => round($data->avg('volumenes.pendiente'), 2),
                    'total' => round($data->avg('volumenes.total'), 2)
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'evolucion' => $data,
                    'totales' => $totales,
                    'promedios' => $promedios,
                    'total_contenedores' => $data->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getEvolucionTotal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la evolución total',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
