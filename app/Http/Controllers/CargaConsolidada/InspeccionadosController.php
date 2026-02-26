<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InspeccionadosController extends Controller
{
    private $table_cotizacion    = 'contenedor_consolidado_cotizacion';
    private $table_contenedor    = 'carga_consolidada_contenedor';
    private $table_tipo_cliente  = 'contenedor_consolidado_tipo_cliente';
    private $table_pagos         = 'contenedor_consolidado_cotizacion_coordinacion_pagos';
    private $table_pagos_concept = 'cotizacion_coordinacion_pagos_concept';
    private $table_proveedores   = 'contenedor_consolidado_cotizacion_proveedores';

    /**
     * GET /carga-consolidada/inspeccionados
     * Vista global de clientes con carga inspeccionada para Contabilidad.
     */
    public function index(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
            }

            // Forzar UTF-8 en la conexion MySQL
            DB::statement('SET NAMES utf8mb4');
            DB::statement('SET CHARACTER SET utf8mb4');
            DB::statement('SET character_set_connection=utf8mb4');

            $search  = $request->get('search', '');
            $perPage = (int) $request->get('limit', 100);
            $page    = (int) $request->get('page', 1);

            $filtersJson = $request->get('filters');
            $filters = is_string($filtersJson) ? json_decode($filtersJson, true) : $filtersJson;
            $filters = is_array($filters) ? $filters : [];

            $estadoInspeccionFiltro = isset($filters['estado_inspeccion']) ? $filters['estado_inspeccion'] : null;
            $estadoPagoFiltro       = isset($filters['estado_pago'])       ? $filters['estado_pago']       : null;
            $fechaInicio            = isset($filters['fecha_inicio'])       ? $filters['fecha_inicio']      : null;
            $fechaFin               = isset($filters['fecha_fin'])          ? $filters['fecha_fin']         : null;

            $query = DB::table($this->table_cotizacion . ' as CC')
                ->select([
                    'CC.id as id_cotizacion',
                    'CC.id_contenedor',
                    'CC.id_contenedor_pago',
                    'CC.nombre',
                    'CC.documento',
                    'CC.telefono',
                    'CC.correo',
                    'CC.monto',
                    'CC.logistica_final',
                    'CC.impuestos_final',
                    'TC.name as tipo_cliente',
                    'CONT.carga',
                    'CONT.f_inicio',
                    DB::raw('(
                        SELECT COUNT(*)
                        FROM ' . $this->table_proveedores . ' prov
                        WHERE prov.id_cotizacion = CC.id
                    ) AS total_proveedores'),
                    DB::raw('(
                        SELECT COUNT(*)
                        FROM ' . $this->table_proveedores . ' prov
                        WHERE prov.id_cotizacion = CC.id
                        AND prov.estados_proveedor IN (\'INSPECTION\', \'LOADED\')
                    ) AS inspeccionados_count'),
                    DB::raw('(
                        SELECT IFNULL(SUM(p.monto), 0)
                        FROM ' . $this->table_pagos . ' p
                        JOIN ' . $this->table_pagos_concept . ' pc ON p.id_concept = pc.id
                        WHERE p.id_cotizacion = CC.id
                        AND pc.name IN (\'LOGISTICA\', \'IMPUESTOS\')
                    ) AS total_pagos'),
                    DB::raw('(
                        SELECT COUNT(*)
                        FROM ' . $this->table_pagos . ' p
                        JOIN ' . $this->table_pagos_concept . ' pc ON p.id_concept = pc.id
                        WHERE p.id_cotizacion = CC.id
                        AND pc.name IN (\'LOGISTICA\', \'IMPUESTOS\')
                    ) AS pagos_count'),
                    DB::raw('(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                \'id_pago\',      p2.id,
                                \'monto\',        p2.monto,
                                \'concepto\',     pc2.name,
                                \'status\',       p2.status,
                                \'payment_date\', p2.payment_date,
                                \'banco\',        p2.banco,
                                \'voucher_url\',  p2.voucher_url
                            )
                        )
                        FROM ' . $this->table_pagos . ' p2
                        LEFT JOIN ' . $this->table_pagos_concept . ' pc2 ON p2.id_concept = pc2.id
                        WHERE p2.id_cotizacion = CC.id
                        AND pc2.name IN (\'LOGISTICA\', \'IMPUESTOS\')
                    ) AS pagos_details'),
                ])
                ->leftJoin($this->table_tipo_cliente . ' as TC', 'TC.id', '=', 'CC.id_tipo_cliente')
                ->join($this->table_contenedor . ' as CONT', 'CONT.id', '=', 'CC.id_contenedor')
                ->whereNull('CC.id_cliente_importacion')
                ->where('CC.estado_cotizador', 'CONFIRMADO')
                ->whereNotNull('CC.estado_cliente')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from($this->table_proveedores . ' as prov_check')
                      ->whereColumn('prov_check.id_cotizacion', 'CC.id')
                      ->whereIn('prov_check.estados_proveedor', ['INSPECTION', 'LOADED']);
                })
                ->orderBy('CONT.f_inicio', 'desc');

            if (!empty($search)) {
                $like = '%' . $search . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('CC.nombre',    'like', $like)
                      ->orWhere('CC.documento', 'like', $like)
                      ->orWhere('CC.telefono',  'like', $like);
                });
            }

            if ($fechaInicio) {
                $query->where('CONT.f_inicio', '>=', $fechaInicio);
            }
            if ($fechaFin) {
                $query->where('CONT.f_inicio', '<=', $fechaFin);
            }

            $results = $query->get();

            $data = $results->map(function ($row) {
                $totalProveedores    = (int) ($row->total_proveedores ?? 0);
                $inspeccionadosCount = (int) ($row->inspeccionados_count ?? 0);

                $estadoInspeccion = 'Pendiente';
                if ($totalProveedores > 0) {
                    if ($inspeccionadosCount >= $totalProveedores) {
                        $estadoInspeccion = 'Completado';
                    } elseif ($inspeccionadosCount > 0) {
                        $estadoInspeccion = 'Inspeccionado';
                    }
                }

                $logistica  = (float) ($row->logistica_final ?? 0);
                $impuestos  = (float) ($row->impuestos_final ?? 0);
                $monto      = ($logistica + $impuestos) > 0
                    ? ($logistica + $impuestos)
                    : (float) ($row->monto ?? 0);
                $totalPagado = (float) ($row->total_pagos ?? 0);
                $pagosCount  = (int)   ($row->pagos_count  ?? 0);

                $estadoPago = 'PENDIENTE';
                if ($pagosCount > 0) {
                    $r = round($totalPagado, 2);
                    $m = round($monto, 2);
                    if ($r < $m)      $estadoPago = 'ADELANTO';
                    elseif ($r == $m) $estadoPago = 'PAGADO';
                    else              $estadoPago = 'SOBREPAGO';
                }

                $pagosDetails = [];
                if (!empty($row->pagos_details)) {
                    $decoded = json_decode($row->pagos_details, true);
                    $pagosDetails = is_array($decoded) ? $decoded : [];
                }

                $anio    = $row->f_inicio ? date('Y', strtotime($row->f_inicio)) : date('Y');
                $campana = ($row->carga ?? '') . ' - ' . $anio;

                return [
                    'id_cotizacion'    => $row->id_cotizacion,
                    'id_contenedor'    => $row->id_contenedor,
                    'id_contenedor_pago' => $row->id_contenedor_pago,
                    'nombre'           => $this->cleanText(ucwords(strtolower($row->nombre ?? ''))),
                    'documento'        => $this->cleanText($row->documento ?? ''),
                    'telefono'         => $this->cleanText($row->telefono  ?? ''),
                    'correo'           => $this->cleanText($row->correo    ?? ''),
                    'tipo_cliente'     => $this->cleanText(ucwords(strtolower($row->tipo_cliente ?? ''))),
                    'campana'          => $campana,
                    'f_inspeccion'     => $row->f_inicio
                        ? Carbon::parse($row->f_inicio)->format('d/m/Y')
                        : '',
                    'estado_inspeccion' => $estadoInspeccion,
                    'estado_pago'       => $estadoPago,
                    'tipo_pago'         => 'Logistica',
                    'monto'             => $monto,
                    'total_pagos'       => $totalPagado,
                    'diferencia'        => round($monto - $totalPagado, 2),
                    'pagos'             => $pagosDetails,
                    'pagos_count'       => $pagosCount,
                ];
            });

            // Filtrar por estado_inspeccion
            if ($estadoInspeccionFiltro && $estadoInspeccionFiltro !== 'todos') {
                $data = $data->filter(function ($item) use ($estadoInspeccionFiltro) {
                    return $item['estado_inspeccion'] === $estadoInspeccionFiltro;
                })->values();
            } else {
                // Por defecto: solo Inspeccionado y Completado
                $data = $data->filter(function ($item) {
                    return in_array($item['estado_inspeccion'], ['Inspeccionado', 'Completado']);
                })->values();
            }

            // Filtrar por estado_pago
            if ($estadoPagoFiltro && $estadoPagoFiltro !== 'todos') {
                $data = $data->filter(function ($item) use ($estadoPagoFiltro) {
                    return $item['estado_pago'] === $estadoPagoFiltro;
                })->values();
            }

            // Paginacion en PHP
            $total    = $data->count();
            $offset   = ($page - 1) * $perPage;
            $lastPage = (int) ceil($total / max($perPage, 1));

            $paginated = $data->slice($offset, $perPage)->values()->map(function ($item, $idx) use ($offset) {
                $item['index'] = $offset + $idx + 1;
                return $item;
            });

            return response()->json([
                'success' => true,
                'data'    => $paginated,
                'pagination' => [
                    'current_page' => $page,
                    'last_page'    => $lastPage,
                    'per_page'     => $perPage,
                    'total'        => $total,
                    'from'         => $total > 0 ? $offset + 1 : 0,
                    'to'           => min($offset + $perPage, $total),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('InspeccionadosController::index: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Limpia el texto de caracteres no UTF-8 para evitar errores de JSON encoding.
     */
    private function cleanText($text)
    {
        if (empty($text)) return '';
        $text = mb_convert_encoding((string) $text, 'UTF-8', 'UTF-8');
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        return $text;
    }
}
