<?php

namespace App\Http\Controllers\CargaConsolidada\CotizacionFinal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\TipoCliente;
use Illuminate\Support\Facades\DB;

class CotizacionFinalController extends Controller
{
    /**
     * Obtiene las cotizaciones finales de un contenedor específico
     */
    public function getContenedorCotizacionesFinales(Request $request, $idContenedor)
    {
        try {
            // Construir la consulta usando Eloquent con campos formateados directamente
            $query = Cotizacion::with('tipoCliente')
                ->select([
                    'contenedor_consolidado_cotizacion.*',
                    
                    'contenedor_consolidado_cotizacion.id as id_cotizacion',
                    'contenedor_consolidado_tipo_cliente.name',
                    DB::raw('UPPER(contenedor_consolidado_cotizacion.nombre)'),
                    DB::raw('UPPER(LEFT(TRIM(contenedor_consolidado_tipo_cliente.name), 1)) || LOWER(SUBSTRING(TRIM(contenedor_consolidado_tipo_cliente.name), 2)) as tipo_cliente_formateado'),
                    DB::raw('FORMAT(contenedor_consolidado_cotizacion.volumen_final, 2) as volumen_final_formateado'),
                    DB::raw('FORMAT(contenedor_consolidado_cotizacion.fob_final, 2) as fob_final_formateado'),
                    DB::raw('FORMAT(contenedor_consolidado_cotizacion.logistica_final, 2) as logistica_final_formateado'),
                    DB::raw('FORMAT(contenedor_consolidado_cotizacion.impuestos_final, 2) as impuestos_final_formateado'),
                    DB::raw('FORMAT(contenedor_consolidado_cotizacion.tarifa_final, 2) as tarifa_final_formateado')
                ])
                ->join('contenedor_consolidado_tipo_cliente', 'contenedor_consolidado_cotizacion.id_tipo_cliente', '=', 'contenedor_consolidado_tipo_cliente.id')
                ->where('id_contenedor', $idContenedor)
                ->whereNotNull('estado_cliente')
                ->where('estado_cotizador', 'CONFIRMADO');

            // Aplicar filtros adicionales si se proporcionan
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nombre', 'LIKE', "%{$search}%")
                      ->orWhere('documento', 'LIKE', "%{$search}%")
                      ->orWhere('correo', 'LIKE', "%{$search}%");
                });
            }

            // Filtrar por estado de cotización final si se proporciona
            if ($request->has('estado_cotizacion_final') && !empty($request->estado_cotizacion_final)) {
                $query->where('estado_cotizacion_final', $request->estado_cotizacion_final);
            }

            // Ordenamiento
            $sortField = $request->input('sort_by', 'fecha');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortField, $sortOrder);

            // Paginación
            $perPage = $request->input('per_page', 10);
            $data = $query->paginate($perPage);

            // Transformar los datos para incluir las columnas específicas
            $transformedData = [];
            $index = 1;

            foreach ($data->items() as $row) {
                $subdata = [
                    'index' => $index,
                    'nombre' => $row->nombre_formateado ?? ucwords(strtolower($row->nombre)),
                    'documento' => $row->documento,
                    'correo' => $row->correo,
                    'telefono' => $row->telefono,
                    'tipo_cliente' =>  ucwords(strtolower($row->name)),
                    'volumen_final' => $row->volumen_final_formateado ?? $row->volumen_final,
                    'fob_final' => $row->fob_final_formateado ?? $row->fob_final,
                    'logistica_final' => $row->logistica_final_formateado ?? $row->logistica_final,
                    'impuestos_final' => $row->impuestos_final_formateado ?? $row->impuestos_final,
                    'tarifa_final' => $row->tarifa_final_formateado ?? $row->tarifa_final,
                    'estado_cotizacion_final' => $row->estado_cotizacion_final,
                    'id_cotizacion' => $row->id_cotizacion
                ];

                $transformedData[] = $subdata;
                $index++;
            }

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem()
                ],
                
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cotizaciones finales: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene las cotizaciones finales con documentación y pagos para un contenedor
     */
    public function getCotizacionFinalDocumentacionPagos(Request $request, $idContenedor)
    {
        try {
            // Construir la consulta usando Eloquent con campos formateados directamente
            $query = Cotizacion::with('tipoCliente')
                ->select([
                    'contenedor_consolidado_cotizacion.*',
                    'contenedor_consolidado_cotizacion.id as id_cotizacion',
                    'TC.name',
                    //get pagos array
                    DB::raw("(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', cccp.id,
                                'id_concept', ccp.id,
                                'name', ccp.name,
                                'monto', cccp.monto,
                                'url', cccp.voucher_url
                            )
                        )
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                        JOIN cotizacion_coordinacion_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccp.name = 'LOGISTICA' OR ccp.name = 'IMPUESTOS')
                    ) AS pagos"),
                    DB::raw("(
                        SELECT IFNULL(SUM(cccp.monto), 0) 
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                        JOIN cotizacion_coordinacion_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccp.name = 'LOGISTICA' OR ccp.name = 'IMPUESTOS')
                    ) AS total_pagos"),
                    DB::raw("(
                        SELECT COUNT(*) 
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                        JOIN cotizacion_coordinacion_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccp.name = 'LOGISTICA' OR ccp.name = 'IMPUESTOS')
                    ) AS pagos_count"),
                    DB::raw('FORMAT(contenedor_consolidado_cotizacion.logistica_final + contenedor_consolidado_cotizacion.impuestos_final, 2) as total_logistica_impuestos')
                ])
                ->leftJoin('contenedor_consolidado_tipo_cliente as TC', 'TC.id', '=', 'contenedor_consolidado_cotizacion.id_tipo_cliente')
                ->where('contenedor_consolidado_cotizacion.id_contenedor', $idContenedor)
                ->whereNotNull('contenedor_consolidado_cotizacion.estado_cliente');

            // Aplicar filtros adicionales si se proporcionan
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('contenedor_consolidado_cotizacion.nombre', 'LIKE', "%{$search}%")
                      ->orWhere('contenedor_consolidado_cotizacion.documento', 'LIKE', "%{$search}%")
                      ->orWhere('contenedor_consolidado_cotizacion.telefono', 'LIKE', "%{$search}%");
                });
            }

            // Ordenamiento
            $sortField = $request->input('sort_by', 'fecha');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortField, $sortOrder);

            // Paginación
            $perPage = $request->input('per_page', 10);
            $data = $query->paginate($perPage);

            // Transformar los datos para incluir las columnas específicas
            $transformedData = [];
            $index = 1;

            foreach ($data->items() as $row) {
                $subdata = [
                    'index' => $index,
                    'nombre' => $row->nombre,
                    'documento' => $row->documento,
                    'telefono' => $row->telefono,
                    'tipo_cliente' => $row->name,
                    'total_logistica_impuestos' => $row->total_logistica_impuestos,
                    'total_pagos' => $row->total_pagos == 0 ? "0.00" : $row->total_pagos,
                    'pagos_count' => $row->pagos_count,
                    'id_cotizacion' => $row->id_cotizacion,
                    'pagos' => $row->pagos  
                ];

                $transformedData[] = $subdata;
                $index++;
            }

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem()
                ],
                
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cotizaciones con documentación y pagos: ' . $e->getMessage()
            ], 500);
        }
    }
}
