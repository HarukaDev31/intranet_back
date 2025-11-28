<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Pago;
use App\Models\CargaConsolidada\PagoConcept;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Traits\WhatsappTrait;
use App\Traits\FileTrait;
use App\Models\CargaConsolidada\Contenedor;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RotuladoParedExport;
use App\Exports\FormatoResumenExport;
use Dompdf\Dompdf;
use Dompdf\Options;
use ZipArchive;

class EntregaController extends Controller
{
    use WhatsappTrait;
    use FileTrait;
    private $table_contenedor_consolidado_cotizacion_coordinacion_pagos = "contenedor_consolidado_cotizacion_coordinacion_pagos";
    private $table_pagos_concept = "cotizacion_coordinacion_pagos_concept";
    private $CONCEPT_PAGO_LOGISTICA = 1;
    private $CONCEPT_PAGO_IMPUESTOS = 2;
    private $CONCEPT_PAGO_DELIVERY = 3;

    /**
     * Listar horarios disponibles (rangos de entrega) para un contenedor.
     * Calcula la disponibilidad como delivery_count - asignados.
     * Devuelve agrupado por fecha.
     */
    public function getHorariosDisponibles(Request $request, $idContenedor)
    {
        // Validar existencia de contenedor
        $contenedor = DB::table('carga_consolidada_contenedor')
            ->select('id', 'carga')
            ->where('id', $idContenedor)
            ->first();

        if (!$contenedor) {
            return response()->json(['message' => 'Contenedor no encontrado'], 404);
        }

        // Opcional: permitir incluir rangos llenos con ?include_full=true
        $includeFull = filter_var($request->query('include_full', 'false'), FILTER_VALIDATE_BOOLEAN);

        // Query base: fechas del contenedor + rangos por fecha
        $rows = DB::table('consolidado_delivery_date as d')
            ->join('consolidado_delivery_range_date as r', 'r.id_date', '=', 'd.id')
            ->leftJoin(
                DB::raw('(SELECT id_date, id_range_date, COUNT(*) as assigned_count
                          FROM consolidado_user_range_delivery
                          GROUP BY id_date, id_range_date) as a'),
                function ($join) {
                    $join->on('a.id_date', '=', 'd.id')
                        ->on('a.id_range_date', '=', 'r.id');
                }
            )
            ->where('d.id_contenedor', $idContenedor)
            ->select([
                'd.id as date_id',
                'd.day',
                'd.month',
                'd.year',
                'r.id as range_id',
                'r.start_time',
                'r.end_time',
                'r.delivery_count',
                'r.is_hidden',
                DB::raw('COALESCE(a.assigned_count, 0) as assigned_count'),
                DB::raw('(r.delivery_count - COALESCE(a.assigned_count, 0)) as available')
            ])
            ->orderBy('d.year')
            ->orderBy('d.month')
            ->orderBy('d.day')
            ->orderBy('r.start_time')
            ->get();

        // Filtrar por disponibilidad si no se incluyen llenos
        if (!$includeFull) {
            $rows = $rows->filter(function ($row) {
                return (int)$row->available > 0;
            })->values();
        }

        // Agrupar por fecha y formatear salida
        $grouped = [];
        foreach ($rows as $row) {
            $dateKey = sprintf('%04d-%02d-%02d', (int)$row->year, (int)$row->month, (int)$row->day);
            if (!isset($grouped[$dateKey])) {
                $grouped[$dateKey] = [
                    'date_id' => $row->date_id,
                    'date' => $dateKey,
                    'day' => (int)$row->day,
                    'month' => (int)$row->month,
                    'year' => (int)$row->year,
                    'slots' => []
                ];
            }
            $grouped[$dateKey]['slots'][] = [
                'range_id' => $row->range_id,
                'start_time' => $row->start_time,
                'end_time' => $row->end_time,
                'capacity' => (int)$row->delivery_count,
                'assigned' => (int)$row->assigned_count,
                'available' => (int)$row->available,
                'is_hidden' => (int)$row->is_hidden,
            ];
        }

        // Reindexar para devolver una lista
        $result = array_values($grouped);

        return response()->json([
            'data' => $result,
            'success' => true,
            'meta' => [
                'include_full' => $includeFull,
                'total_dates' => count($result),
            ]
        ]);
    }
    public function getHorariosDisponiblesClientes(Request $request, $idContenedor)
    {
        // Validar existencia de contenedor
        $contenedor = DB::table('carga_consolidada_contenedor')
            ->select('id', 'carga')
            ->where('id', $idContenedor)
            ->first();

        if (!$contenedor) {
            return response()->json(['message' => 'Contenedor no encontrado'], 404);
        }

        // Query base: fechas del contenedor + rangos por fecha (solo rangos sin formularios Lima asociados)
        $rows = DB::table('consolidado_delivery_date as d')
            ->join('consolidado_delivery_range_date as r', 'r.id_date', '=', 'd.id')
            ->leftJoin(
                DB::raw('(SELECT id_date, id_range_date, COUNT(*) as assigned_count
                          FROM consolidado_user_range_delivery
                          GROUP BY id_date, id_range_date) as a'),
                function ($join) {
                    $join->on('a.id_date', '=', 'd.id')
                        ->on('a.id_range_date', '=', 'r.id');
                }
            )
            ->leftJoin('consolidado_delivery_form_lima as lima', function ($join) use ($idContenedor) {
                $join->on('lima.id_range_date', '=', 'r.id')
                    ->where('lima.id_contenedor', '=', $idContenedor);
            })
            ->where('d.id_contenedor', $idContenedor)
            ->whereNull('lima.id')
            ->where('r.is_hidden', 0)
            ->select([
                'd.id as date_id',
                'd.day',
                'd.month',
                'd.year',
                'r.id as range_id',
                'r.start_time',
                'r.end_time',
                'r.delivery_count',
                'r.is_hidden',
                DB::raw('COALESCE(a.assigned_count, 0) as assigned_count'),
                DB::raw('(r.delivery_count - COALESCE(a.assigned_count, 0)) as available')
            ])

            ->orderBy('d.year')
            ->orderBy('d.month')
            ->orderBy('d.day')
            ->orderBy('r.start_time')
            ->get();

        // Agrupar por fecha y formatear salida
        $grouped = [];
        foreach ($rows as $row) {
            $dateKey = sprintf('%04d-%02d-%02d', (int)$row->year, (int)$row->month, (int)$row->day);
            if (!isset($grouped[$dateKey])) {
                $grouped[$dateKey] = [
                    'date_id' => $row->date_id,
                    'date' => $dateKey,
                    'day' => (int)$row->day,
                    'month' => (int)$row->month,
                    'year' => (int)$row->year,
                    'slots' => []
                ];
            }
            $grouped[$dateKey]['slots'][] = [
                'range_id' => $row->range_id,
                'start_time' => $row->start_time,
                'end_time' => $row->end_time,
                'capacity' => (int)$row->delivery_count,
                'assigned' => (int)$row->assigned_count,
                'available' => (int)$row->available,
            ];
        }

        // Reindexar para devolver una lista
        $result = array_values($grouped);

        return response()->json([
            'data' => $result,
            'success' => true,
            'meta' => [
                'total_dates' => count($result),
                'total_available_slots' => $rows->sum('available'),
                'filter' => 'rangos_sin_formularios_lima'
            ]
        ]);
    }

    public function getHeaders($idContenedor)
    {
        $contenedor = DB::table('carga_consolidada_contenedor')
            ->select('carga')
            ->where('id', $idContenedor)
            ->first();

        if (!$contenedor) {
            return response()->json(['message' => 'Contenedor no encontrado'], 404);
        }

        return response()->json(['data' => $contenedor, 'success' => true]);
    }

    /**
     * Crear una fecha de entrega para un contenedor.
     * Acepta { day, month, year } o un único { date: 'YYYY-MM-DD' }.
     */
    public function createFecha(Request $request, $idContenedor)
    {
        // Validar contenedor
        $contenedor = DB::table('carga_consolidada_contenedor')->where('id', $idContenedor)->exists();
        if (!$contenedor) {
            return response()->json(['message' => 'Contenedor no encontrado'], 404);
        }

        // Normalizar entrada
        $day = $request->input('day');
        $month = $request->input('month');
        $year = $request->input('year');
        $dateStr = $request->input('date'); // YYYY-MM-DD

        if ($dateStr) {
            $ts = strtotime($dateStr);
            if (!$ts) {
                return response()->json(['message' => 'Formato de fecha inválido. Use YYYY-MM-DD.'], 422);
            }
            $day = (int)date('d', $ts);
            $month = (int)date('m', $ts);
            $year = (int)date('Y', $ts);
        }

        if (!($day && $month && $year)) {
            return response()->json(['message' => 'Debe proporcionar day, month y year o un date (YYYY-MM-DD).'], 422);
        }

        if (!checkdate((int)$month, (int)$day, (int)$year)) {
            return response()->json(['message' => 'Fecha inválida proporcionada.'], 422);
        }

        // Evitar duplicados por contenedor + fecha
        $existing = DB::table('consolidado_delivery_date')
            ->where('id_contenedor', $idContenedor)
            ->where('day', $day)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($existing) {
            return response()->json([
                'data' => [
                    'id' => $existing->id,
                    'day' => (int)$existing->day,
                    'month' => (int)$existing->month,
                    'year' => (int)$existing->year,
                    'duplicated' => true,
                ],
                'success' => true,
                'message' => 'La fecha ya existe para este contenedor.'
            ], 200);
        }

        $id = DB::table('consolidado_delivery_date')->insertGetId([
            'id_contenedor' => $idContenedor,
            'day' => (int)$day,
            'month' => (int)$month,
            'year' => (int)$year,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'id' => $id,
                'day' => (int)$day,
                'month' => (int)$month,
                'year' => (int)$year,
            ],
            'success' => true
        ], 201);
    }

    /**
     * Eliminar una fecha de entrega si no tiene asignaciones.
     */
    public function deleteFecha($idContenedor, $idFecha)
    {
        $fecha = DB::table('consolidado_delivery_date')
            ->where('id', $idFecha)
            ->where('id_contenedor', $idContenedor)
            ->first();

        if (!$fecha) {
            return response()->json(['message' => 'Fecha no encontrada para este contenedor'], 404);
        }

        $hasAssignments = DB::table('consolidado_user_range_delivery')
            ->where('id_date', $idFecha)
            ->exists();

        if ($hasAssignments) {
            return response()->json(['message' => 'No se puede eliminar la fecha: tiene entregas asignadas'], 422);
        }

        DB::table('consolidado_delivery_date')->where('id', $idFecha)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Crear un rango de horario para una fecha específica.
     */
    public function createRango(Request $request, $idContenedor, $idFecha)
    {
        $request->validate([
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'delivery_count' => 'required|integer|min:1',
        ]);

        $fecha = DB::table('consolidado_delivery_date')
            ->where('id', $idFecha)
            ->where('id_contenedor', $idContenedor)
            ->first();
        if (!$fecha) {
            return response()->json(['message' => 'Fecha no encontrada para este contenedor'], 404);
        }

        $start = $this->normalizeTime($request->input('start_time'));
        $end = $this->normalizeTime($request->input('end_time'));

        if (!$start || !$end) {
            return response()->json(['message' => 'Formato de hora inválido. Use HH:MM o HH:MM:SS'], 422);
        }
        if ($end <= $start) {
            return response()->json(['message' => 'La hora de fin debe ser mayor a la hora de inicio'], 422);
        }

        // Validar traslape
        $overlap = DB::table('consolidado_delivery_range_date')
            ->where('id_date', $idFecha)
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            })
            ->exists();
        if ($overlap) {
            return response()->json(['message' => 'El horario se superpone con otro existente'], 422);
        }

        $rangeId = DB::table('consolidado_delivery_range_date')->insertGetId([
            'id_date' => $idFecha,
            'start_time' => $start,
            'end_time' => $end,
            'delivery_count' => (int)$request->input('delivery_count'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'id' => $rangeId,
                'id_date' => (int)$idFecha,
                'start_time' => $start,
                'end_time' => $end,
                'delivery_count' => (int)$request->input('delivery_count'),
            ],
            'success' => true
        ], 201);
    }

    /**
     * Actualizar un rango de horario.
     */
    public function updateRango(Request $request, $idContenedor, $idFecha, $idRango)
    {
        $request->validate([
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'delivery_count' => 'required|integer|min:1',
        ]);

        $fecha = DB::table('consolidado_delivery_date')
            ->where('id', $idFecha)
            ->where('id_contenedor', $idContenedor)
            ->first();
        if (!$fecha) {
            return response()->json(['message' => 'Fecha no encontrada para este contenedor'], 404);
        }

        $rango = DB::table('consolidado_delivery_range_date')
            ->where('id', $idRango)
            ->where('id_date', $idFecha)
            ->first();
        if (!$rango) {
            return response()->json(['message' => 'Rango no encontrado para esta fecha'], 404);
        }

        $start = $this->normalizeTime($request->input('start_time'));
        $end = $this->normalizeTime($request->input('end_time'));
        $capacity = (int)$request->input('delivery_count');

        if (!$start || !$end) {
            return response()->json(['message' => 'Formato de hora inválido. Use HH:MM o HH:MM:SS'], 422);
        }
        if ($end <= $start) {
            return response()->json(['message' => 'La hora de fin debe ser mayor a la hora de inicio'], 422);
        }

        // Asignados actuales en este rango
        $assigned = (int) DB::table('consolidado_user_range_delivery')
            ->where('id_date', $idFecha)
            ->where('id_range_date', $idRango)
            ->count();
        if ($capacity < $assigned) {
            return response()->json(['message' => 'La capacidad no puede ser menor a las asignaciones actuales (' . $assigned . ')'], 422);
        }

        // Validar traslape excluyendo el propio rango
        $overlap = DB::table('consolidado_delivery_range_date')
            ->where('id_date', $idFecha)
            ->where('id', '!=', $idRango)
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            })
            ->exists();
        if ($overlap) {
            return response()->json(['message' => 'El horario se superpone con otro existente'], 422);
        }

        DB::table('consolidado_delivery_range_date')
            ->where('id', $idRango)
            ->update([
                'start_time' => $start,
                'end_time' => $end,
                'delivery_count' => $capacity,
                'updated_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }

    /**
     * Eliminar un rango de horario si no tiene asignaciones.
     */
    public function deleteRango($idContenedor, $idFecha, $idRango)
    {
        $fecha = DB::table('consolidado_delivery_date')
            ->where('id', $idFecha)
            ->where('id_contenedor', $idContenedor)
            ->exists();
        if (!$fecha) {
            return response()->json(['message' => 'Fecha no encontrada para este contenedor'], 404);
        }

        $rango = DB::table('consolidado_delivery_range_date')
            ->where('id', $idRango)
            ->where('id_date', $idFecha)
            ->first();
        if (!$rango) {
            return response()->json(['message' => 'Rango no encontrado para esta fecha'], 404);
        }

        $hasAssignments = DB::table('consolidado_user_range_delivery')
            ->where('id_date', $idFecha)
            ->where('id_range_date', $idRango)
            ->exists();

        if ($hasAssignments) {
            return response()->json(['message' => 'No se puede eliminar el rango: tiene entregas asignadas'], 422);
        }

        DB::table('consolidado_delivery_range_date')->where('id', $idRango)->delete();
        return response()->json(['success' => true]);
    }

    // Normaliza "HH:MM" -> "HH:MM:00"; valida patrón básico
    private function normalizeTime($value)
    {
        if (!is_string($value)) return null;
        $value = trim($value);
        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value . ':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }
        return null;
    }

    /**
     * Formatea números de teléfono: elimina espacios/caracteres no numéricos y
     * si el número empieza por el código de país 51 (o 0051) lo elimina.
     * Ejemplo: "+51 919 000 000" -> "919000000"
     */
    private function formatPhoneNumber($raw)
    {
        if (is_null($raw)) return '';
        $s = (string)$raw;
        // eliminar espacios y cualquier caracter que no sea dígito
        $digits = preg_replace('/\D+/', '', $s);
        if ($digits === null) return '';

        // manejar prefijos internacionales comunes
        if (strpos($digits, '0051') === 0) {
            $digits = substr($digits, 4);
        } elseif (strpos($digits, '51') === 0) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }
    public function getClientesEntrega(Request $request, $idContenedor)
    {
        // Lo obtiene de la tabla de clientes asociados al contenedor
        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->join('contenedor_consolidado_tipo_cliente as TC', 'TC.id', '=', 'CC.id_tipo_cliente')
            // Formularios (Lima y Provincia) asociados por id_cotizacion y mismo contenedor
            ->leftJoin('consolidado_delivery_form_lima as L', function ($join) use ($idContenedor) {
                $join->on('L.id_cotizacion', '=', 'CC.id')
                    ->where('L.id_contenedor', '=', $idContenedor);
            })
            ->leftJoin('consolidado_delivery_form_province as P', function ($join) use ($idContenedor) {
                $join->on('P.id_cotizacion', '=', 'CC.id')
                    ->where('P.id_contenedor', '=', $idContenedor);
            })

            ->where('CC.id_contenedor', $idContenedor)
            ->whereNotNull('CC.estado_cliente')
            ->whereNull('CC.id_cliente_importacion')
            ->where('CC.estado_cotizador', 'CONFIRMADO')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('contenedor_consolidado_cotizacion_proveedores')
                    ->whereColumn('contenedor_consolidado_cotizacion_proveedores.id_cotizacion', 'CC.id');
            })
            ->select([
                'CC.*',
                'TC.name as name',
                // Tipo de formulario: 0 = Provincia, 1 = Lima (prioriza Provincia si existen ambos)
                DB::raw('CASE WHEN P.id IS NOT NULL THEN 0 WHEN L.id IS NOT NULL THEN 1 ELSE NULL END as type_form'),
                // voucher_doc normalizado según type_form (0 Provincia, 1 Lima)
                DB::raw('CASE WHEN P.id IS NOT NULL THEN P.voucher_doc WHEN L.id IS NOT NULL THEN L.voucher_doc ELSE NULL END as voucher_doc'),
                DB::raw('(CC.logistica_final + CC.impuestos_final) as total_logistica_impuestos'),
                DB::raw("(
                        SELECT IFNULL(SUM(cccp.monto), 0) 
                        FROM {$this->table_contenedor_consolidado_cotizacion_coordinacion_pagos} cccp
                        JOIN {$this->table_pagos_concept} ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = CC.id
                        AND (ccp.name = 'LOGISTICA' OR ccp.name = 'IMPUESTOS')
                    ) AS total_pagos"),
                DB::raw("(
                    SELECT IFNULL(JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id_pago', cccp2.id,
                            'monto', cccp2.monto,
                            'concepto', ccp2.name,
                            'status', cccp2.status,
                            'payment_date', cccp2.payment_date,
                            'banco', cccp2.banco,
                            'voucher_url', cccp2.voucher_url
                        )
                    ), JSON_ARRAY())
                    FROM {$this->table_contenedor_consolidado_cotizacion_coordinacion_pagos} cccp2
                    JOIN {$this->table_pagos_concept} ccp2 ON cccp2.id_concept = ccp2.id
                    WHERE cccp2.id_cotizacion = CC.id
                    AND (ccp2.id = {$this->CONCEPT_PAGO_LOGISTICA} OR ccp2.id = {$this->CONCEPT_PAGO_IMPUESTOS})
                    ORDER BY cccp2.payment_date ASC, cccp2.id ASC
                ) AS pagos_details"),
                //same but pagos concept delivery
                DB::raw("(
                    SELECT IFNULL(JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id_pago', cccp2.id,
                            'monto', cccp2.monto,
                            'concepto', ccp2.name,
                            'status', cccp2.status,
                            'payment_date', cccp2.payment_date,
                            'banco', cccp2.banco,
                            'voucher_url', cccp2.voucher_url
                        )
                    ), JSON_ARRAY())
                    FROM {$this->table_contenedor_consolidado_cotizacion_coordinacion_pagos} cccp2
                    JOIN {$this->table_pagos_concept} ccp2 ON cccp2.id_concept = ccp2.id
                    WHERE cccp2.id_cotizacion = CC.id
                    AND ccp2.id = {$this->CONCEPT_PAGO_DELIVERY}
                    ORDER BY cccp2.payment_date ASC, cccp2.id ASC
                ) AS pagos_details_delivery"),
            ]);

        // Filtros adicionales
        if ($request->filled('search')) {
            $search = trim((string)$request->input('search'));
            $searchDigits = preg_replace('/\D+/', '', $search);

            $query->where(function ($q) use ($search, $searchDigits) {
                $q->where('CC.nombre', 'LIKE', "%{$search}%")
                    ->orWhere('CC.documento', 'LIKE', "%{$search}%")
                    ->orWhere('CC.correo', 'LIKE', "%{$search}%");

                // Si la búsqueda contiene dígitos, comparar con la versión numérica del teléfono
                if ($searchDigits !== '') {
                    // Normalizar teléfono en SQL removiendo espacios, guiones, paréntesis y '+'
                    $normalized = 'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(CC.telefono, " ", ""), "-", ""), "(", ""), ")", ""), "+", "")';
                    // Comparar tanto contra la cadena de dígitos como contra la versión con prefijo 51 (código país)
                    $q->orWhereRaw("{$normalized} LIKE ?", ["%{$searchDigits}%"]);
                    $q->orWhereRaw("{$normalized} LIKE ?", ["%51{$searchDigits}%"]);
                }
            });
        }

        // Orden y paginación
        $page = (int) $request->input('currentPage', 1);
        $perPage = (int) $request->input('itemsPerPage', 100);
        $data = $query->orderBy('CC.id', 'asc')->paginate($perPage, ['*'], 'page', $page);


        // Agregar fotos de conformidad (hasta 2) y el total por cada fila
        $items = $data->items();
        foreach ($items as $row) {
            $row->conformidad = [];
            $row->conformidad_count = 0;
            $typeForm = isset($row->type_form) ? (int)$row->type_form : null;
            if ($typeForm !== null) {
                // type_form: 0 = Provincia, 1 = Lima
                $tableName = $typeForm === 1
                    ? 'consolidado_delivery_form_lima_conformidad'
                    : 'consolidado_delivery_form_province_conformidad';

                // Obtener total y hasta 2 últimas fotos
                $total = DB::table($tableName)
                    ->where('id_cotizacion', $row->id)
                    ->where('id_contenedor', $row->id_contenedor)
                    ->count();
                $photos = DB::table($tableName)
                    ->select('id', 'file_path', 'file_type', 'file_size', 'file_original_name', 'created_at')
                    ->where('id_cotizacion', $row->id)
                    ->where('id_contenedor', $row->id_contenedor)
                    ->orderByDesc('created_at')
                    ->limit(2)
                    ->get();

                $row->conformidad = $photos->map(function ($p) {
                    return [
                        'id' => (int)$p->id,
                        'file_path' => $p->file_path,
                        'file_url' => $this->generateImageUrl($p->file_path),
                        'file_type' => $p->file_type,
                        'file_size' => $p->file_size ? (int)$p->file_size : null,
                        'file_original_name' => $p->file_original_name,
                        'created_at' => $p->created_at,
                    ];
                })->toArray();
                $row->conformidad_count = $total;
            }
        }

        return response()->json([
            'data' => $data->items(),
            'success' => true,
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem()
            ]
        ]);
    }
    public function sendForm($idContenedor)
    {
        // Lógica para manejar el envío del formulario de entrega
    }
    public function getEntregas(Request $request, $idContenedor)
    {
        // Subconsulta: sumar cbm y qty por id_cotizacion (puede haber múltiples proveedores por cotización)
        $proveedoresAgg = DB::table('contenedor_consolidado_cotizacion_proveedores as CP')
            ->select(
                'CP.id_cotizacion',
                DB::raw('SUM(COALESCE(CP.cbm_total_china, CP.cbm_total, 0)) as sum_cbm_china'),
                DB::raw('SUM(COALESCE(CP.qty_box_china, CP.qty_box, 0)) as sum_qty_box')
            )
            ->where('CP.id_contenedor', $idContenedor)
            ->groupBy('CP.id_cotizacion');

        // Subconsulta: asignación de rango/fecha por cotización (si hubiera varias, tomamos la última por id)
        $asignacionAgg = DB::table('consolidado_user_range_delivery as UR')
            ->select(
                'UR.id_cotizacion',
                DB::raw('MAX(UR.id_date) as id_date'),
                DB::raw('MAX(UR.id_range_date) as id_range_date')
            )
            ->groupBy('UR.id_cotizacion');

        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->join('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
            ->leftJoin('consolidado_delivery_form_lima as L', function ($join) use ($idContenedor) {
                $join->on('L.id_cotizacion', '=', 'CC.id')
                    ->where('L.id_contenedor', '=', $idContenedor);
            })
            ->leftJoin('consolidado_delivery_form_province as P', function ($join) use ($idContenedor) {
                $join->on('P.id_cotizacion', '=', 'CC.id')
                    ->where('P.id_contenedor', '=', $idContenedor);
            })
            // Departamento (solo aplica para formulario de provincia)
            ->leftJoin('departamento as DPT', 'DPT.ID_Departamento', '=', 'P.id_department')
            // Usuarios asociados a los formularios
            ->leftJoin('users as UL', 'UL.id', '=', 'L.id_user')
            ->leftJoin('users as UP', 'UP.id', '=', 'P.id_user')
            // Sumas de proveedores por cotización
            ->leftJoinSub($proveedoresAgg, 'CPA', function ($join) {
                $join->on('CPA.id_cotizacion', '=', 'CC.id');
            })
            // Asignación de horario de entrega por cotización
            ->leftJoinSub($asignacionAgg, 'UR2', function ($join) {
                $join->on('UR2.id_cotizacion', '=', 'CC.id');
            })
            ->leftJoin('consolidado_delivery_date as D2', function ($join) use ($idContenedor) {
                $join->on('D2.id', '=', 'UR2.id_date')
                    ->where('D2.id_contenedor', '=', $idContenedor);
            })
            ->leftJoin('consolidado_delivery_range_date as R2', 'R2.id', '=', 'UR2.id_range_date')
            ->where('CC.id_contenedor', $idContenedor)
            ->whereNotNull('CC.estado_cliente')
            ->whereNull('CC.id_cliente_importacion')
            ->where('CC.estado_cotizador', 'CONFIRMADO')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('contenedor_consolidado_cotizacion_proveedores')
                    ->whereColumn('contenedor_consolidado_cotizacion_proveedores.id_cotizacion', 'CC.id');
            })
            // Solo filas que tengan algún formulario asociado
            ->where(function ($q) {
                $q->whereNotNull('L.id')
                    ->orWhereNotNull('P.id');
            })
            ->select([
                'C.*',
                'CC.*',
                // Agregados por cotización desde proveedores
                DB::raw('COALESCE(CPA.sum_cbm_china, 0) as cbm_total_china'),
                DB::raw('COALESCE(CPA.sum_qty_box, 0) as qty_box_china'),
                // Tipo de formulario 0 provincia / 1 lima
                DB::raw('CASE WHEN P.id IS NOT NULL THEN 0 WHEN L.id IS NOT NULL THEN 1 ELSE NULL END as type_form'),
                DB::raw('CASE WHEN P.id IS NOT NULL THEN P.voucher_doc WHEN L.id IS NOT NULL THEN L.voucher_doc ELSE NULL END as voucher_doc'),
                // Usuario del formulario (normalizado)
                DB::raw('CASE WHEN P.id IS NOT NULL THEN P.id_user WHEN L.id IS NOT NULL THEN L.id_user ELSE NULL END as form_user_id'),
                DB::raw('CASE WHEN P.id IS NOT NULL THEN UP.name WHEN L.id IS NOT NULL THEN UL.name ELSE NULL END as form_user_name'),
                DB::raw('CASE WHEN P.id IS NOT NULL THEN UP.email WHEN L.id IS NOT NULL THEN UL.email ELSE NULL END as form_user_email'),
                // Nombre del departamento si es Provincia
                DB::raw('CASE WHEN P.id IS NOT NULL THEN DPT.No_Departamento ELSE NULL END as department_name'),
                // Ruc de la agencia si es de Provincia
                DB::raw('CASE WHEN P.id IS NOT NULL THEN P.agency_ruc ELSE NULL END as agency_ruc'),
                DB::raw('CASE WHEN P.id IS NOT NULL THEN P.agency_name ELSE NULL END as agency_name'),
                //R_docs si es de Provincia
                DB::raw('CASE WHEN P.id IS NOT NULL THEN P.r_doc ELSE NULL END as r_doc'),
                DB::raw('CASE WHEN P.id IS NOT NULL THEN P.r_name ELSE NULL END as r_name'),
                // Pick doc si es de Lima
                DB::raw('CASE WHEN L.id IS NOT NULL THEN L.pick_doc ELSE NULL END as pick_doc'),
                DB::raw('CASE WHEN L.id IS NOT NULL THEN L.pick_name ELSE NULL END as pick_name'),
                // Datos de entrega desde la asignación (fecha y hora)
                DB::raw("CASE WHEN UR2.id_date IS NOT NULL THEN CONCAT(D2.year, '-', LPAD(D2.month, 2, '0'), '-', LPAD(D2.day, 2, '0')) ELSE NULL END as delivery_date"),
                DB::raw('R2.start_time as delivery_start_time'),
                DB::raw('R2.end_time as delivery_end_time'),
                DB::raw('UR2.id_date as delivery_date_id'),
                DB::raw('UR2.id_range_date as delivery_range_id'),
            ]);

        // Búsqueda
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('CC.nombre', 'LIKE', "%{$search}%")
                    ->orWhere('CC.documento', 'LIKE', "%{$search}%")
                    ->orWhere('CC.telefono', 'LIKE', "%{$search}%");
            });
        }

        //Filtros adicionales (fecha de inicio y fin)
        // Soportar dos formas desde el front:
        //  - parámetros separados: ?tipo_entrega=Lima&estado_entrega=ENTREGADO&fecha_inicio=...
        //  - un único parámetro `filters` con JSON: ?filters={"tipo_entrega":"Lima","fecha_inicio":"2025-11-12"}
        $filtersRaw = $request->query('filters', null);
        $filters = [];
        if ($filtersRaw) {
            $decoded = json_decode($filtersRaw, true);
            if (is_array($decoded)) $filters = $decoded;
        }

        // Merge direct params (direct params override JSON)
        $directKeys = ['tipo_entrega', 'estado_entrega', 'fecha_inicio', 'fecha_fin', 'estado_cotizador'];
        foreach ($directKeys as $k) {
            $v = $request->query($k, null);
            if (!is_null($v) && $v !== '') $filters[$k] = $v;
        }

        // Whitelist - only allow expected filters
        $allowed = ['tipo_entrega', 'estado_entrega', 'fecha_inicio', 'fecha_fin', 'estado_cotizador'];
        $filters = array_intersect_key($filters, array_flip($allowed));

        // Aplicar filtros conocidos
        if (!empty($filters['tipo_entrega'])) {
            $tipo = strtoupper(trim($filters['tipo_entrega']));
            if ($tipo === 'LIMA') {
                $query->whereNotNull('L.id');
            } elseif (in_array($tipo, ['PROVINCIA', 'PROVINCE'])) {
                $query->whereNotNull('P.id');
            }
        }

        if (!empty($filters['estado_cotizador'])) {
            $query->where('CC.estado_cotizador', $filters['estado_cotizador']);
        }

        // Filtrar por fecha de entrega asignada (usa D2.year/month/day)
        if (!empty($filters['fecha_inicio'])) {
            $fechaInicio = $filters['fecha_inicio'];
            $query->whereRaw("CONCAT(D2.year,'-',LPAD(D2.month,2,'0'),'-',LPAD(D2.day,2,'0')) >= ?", [$fechaInicio]);
        }
        if (!empty($filters['fecha_fin'])) {
            $fechaFin = $filters['fecha_fin'];
            $query->whereRaw("CONCAT(D2.year,'-',LPAD(D2.month,2,'0'),'-',LPAD(D2.day,2,'0')) <= ?", [$fechaFin]);
        }

        // Estado de entrega: implementamos una convención mínima
        // 'ENTREGADO' => existe al menos una foto de conformidad (tabla *_conformidad)
        if (!empty($filters['estado_entrega'])) {
            $ee = strtoupper(trim($filters['estado_entrega']));
            if ($ee === 'ENTREGADO') {
                $query->where(function ($q) {
                    $q->whereExists(function ($sq) {
                        $sq->select(DB::raw(1))
                            ->from('consolidado_delivery_form_lima_conformidad')
                            ->whereColumn('consolidado_delivery_form_lima_conformidad.id_cotizacion', 'CC.id');
                    })->orWhereExists(function ($sq) {
                        $sq->select(DB::raw(1))
                            ->from('consolidado_delivery_form_province_conformidad')
                            ->whereColumn('consolidado_delivery_form_province_conformidad.id_cotizacion', 'CC.id');
                    });
                });
            } elseif (in_array($ee, ['PENDIENTE', 'NO_ENTREGADO', 'NOENTREGADO'])) {
                $query->where(function ($q) {
                    $q->whereNotExists(function ($sq) {
                        $sq->select(DB::raw(1))
                            ->from('consolidado_delivery_form_lima_conformidad')
                            ->whereColumn('consolidado_delivery_form_lima_conformidad.id_cotizacion', 'CC.id');
                    })->whereNotExists(function ($sq) {
                        $sq->select(DB::raw(1))
                            ->from('consolidado_delivery_form_province_conformidad')
                            ->whereColumn('consolidado_delivery_form_province_conformidad.id_cotizacion', 'CC.id');
                    });
                });
            }
        }

        // Orden: Provincia primero (type_form=0), luego por fecha/hora de entrega (asignadas primero), luego por ID
        $page = (int) $request->input('currentPage', 1);
        $perPage = (int) $request->input('itemsPerPage', 100);

        $query
            // Provincia (P) primero, luego Lima (L). Si ninguno, al final.
            ->orderByRaw("CASE WHEN P.id IS NOT NULL THEN 0 WHEN L.id IS NOT NULL THEN 1 ELSE 2 END ASC")
            // Con fecha asignada primero (no nulos), luego sin asignación
            ->orderByRaw("CASE WHEN UR2.id_date IS NULL THEN 1 ELSE 0 END ASC")
            // Por fecha concreta (año, mes, día)
            ->orderBy('D2.year', 'asc')
            ->orderBy('D2.month', 'asc')
            ->orderBy('D2.day', 'asc')
            // Con hora asignada primero, luego sin hora
            ->orderByRaw("CASE WHEN R2.start_time IS NULL THEN 1 ELSE 0 END ASC")
            ->orderBy('R2.start_time', 'asc')
            // Estable para empates
            ->orderBy('CC.id', 'asc');

        $data = $query->paginate($perPage, ['*'], 'page', $page);

        // Agregar fotos de conformidad (hasta 2) y el total por cada fila
        $items = $data->items();
        foreach ($items as $row) {
            $row->conformidad = [];
            $row->conformidad_count = 0;
            $typeForm = isset($row->type_form) ? (int)$row->type_form : null;
            if ($typeForm !== null) {
                // type_form: 0 = Provincia, 1 = Lima
                $tableName = $typeForm === 1
                    ? 'consolidado_delivery_form_lima_conformidad'
                    : 'consolidado_delivery_form_province_conformidad';

                // Obtener total y hasta 2 últimas fotos
                $total = DB::table($tableName)
                    ->where('id_cotizacion', $row->id)
                    ->where('id_contenedor', $row->id_contenedor)
                    ->count();
                $photos = DB::table($tableName)
                    ->select('id', 'file_path', 'file_type', 'file_size', 'file_original_name', 'created_at')
                    ->where('id_cotizacion', $row->id)
                    ->where('id_contenedor', $row->id_contenedor)
                    ->orderByDesc('created_at')
                    ->limit(2)
                    ->get();

                $row->conformidad = $photos->map(function ($p) {
                    return [
                        'id' => (int)$p->id,
                        'file_path' => $p->file_path,
                        'file_url' => $this->generateImageUrl($p->file_path),
                        'file_type' => $p->file_type,
                        'file_size' => $p->file_size ? (int)$p->file_size : null,
                        'file_original_name' => $p->file_original_name,
                        'created_at' => $p->created_at,
                    ];
                })->toArray();
                $row->conformidad_count = $total;
            }
        }

        // Calcular estadísticas de headers
        // Subconsulta para sumar bultos de proveedores embarcados (no importados) por cotización
        $proveedoresBultos = DB::table('contenedor_consolidado_cotizacion_proveedores as CP')
            ->select(
                'CP.id_cotizacion',
                DB::raw('SUM(COALESCE(CP.qty_box_china, CP.qty_box, 0)) as sum_qty_box')
            )
            ->where('CP.id_contenedor', $idContenedor)
            ->groupBy('CP.id_cotizacion');

        $statsProvincia = DB::table('contenedor_consolidado_cotizacion as CC')
            ->join('consolidado_delivery_form_province as P', function ($join) use ($idContenedor) {
                $join->on('P.id_cotizacion', '=', 'CC.id')
                    ->where('P.id_contenedor', '=', $idContenedor);
            })
            ->leftJoinSub($proveedoresBultos, 'PB', function ($join) {
                $join->on('PB.id_cotizacion', '=', 'CC.id');
            })
            ->where('CC.id_contenedor', $idContenedor)
            ->whereNotNull('CC.estado_cliente')
            ->whereNull('CC.id_cliente_importacion')
            ->where('CC.estado_cotizador', 'CONFIRMADO')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('contenedor_consolidado_cotizacion_proveedores')
                    ->whereColumn('contenedor_consolidado_cotizacion_proveedores.id_cotizacion', 'CC.id');
            })
            ->select([
                DB::raw('COUNT(DISTINCT CC.id) as qty'),
                DB::raw('COALESCE(SUM(CC.volumen), 0) as cbm'),
                DB::raw('COALESCE(SUM(PB.sum_qty_box), 0) as bultos')
            ])
            ->first();

        $statsLima = DB::table('contenedor_consolidado_cotizacion as CC')
            ->join('consolidado_delivery_form_lima as L', function ($join) use ($idContenedor) {
                $join->on('L.id_cotizacion', '=', 'CC.id')
                    ->where('L.id_contenedor', '=', $idContenedor);
            })
            ->leftJoinSub($proveedoresBultos, 'PB', function ($join) {
                $join->on('PB.id_cotizacion', '=', 'CC.id');
            })
            ->where('CC.id_contenedor', $idContenedor)
            ->whereNotNull('CC.estado_cliente')
            ->whereNull('CC.id_cliente_importacion')
            ->where('CC.estado_cotizador', 'CONFIRMADO')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('contenedor_consolidado_cotizacion_proveedores')
                    ->whereColumn('contenedor_consolidado_cotizacion_proveedores.id_cotizacion', 'CC.id');
            })
            ->select([
                DB::raw('COUNT(DISTINCT CC.id) as qty'),
                DB::raw('COALESCE(SUM(CC.volumen), 0) as cbm'),
                DB::raw('COALESCE(SUM(PB.sum_qty_box), 0) as bultos')
            ])
            ->first();

        // Preparar headers
        $headers = [
            [
                'value' => (int)($statsProvincia->qty ?? 0),
                'label' => 'Qty Provincia',
                'icon' => 'i-heroicons-users'
            ],
            [
                'value' => (int)($statsLima->qty ?? 0),
                'label' => 'Qty Lima',
                'icon' => 'i-heroicons-users'
            ],
            [
                'value' => number_format((float)($statsProvincia->cbm ?? 0), 2, '.', ''),
                'label' => 'CBM Provincia',
                'icon' => 'mage:box-3d'
            ],
            [
                'value' => number_format((float)($statsLima->cbm ?? 0), 2, '.', ''),
                'label' => 'CBM Lima',
                'icon' => 'mage:box-3d'
            ],
            [
                'value' => (int)($statsProvincia->bultos ?? 0),
                'label' => 'Bultos Provincia',
                'icon' => 'bi:boxes'
            ],
            [
                'value' => (int)($statsLima->bultos ?? 0),
                'label' => 'Bultos Lima',
                'icon' => 'bi:boxes'
            ]
        ];

        return response()->json([
            'data' => $items,
            'headers' => $headers,
            'success' => true,
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem()
            ]
        ]);
    }

    /**
     * Genera y descarga un Excel "ROTULADO PARED" con las columnas:
     * Cliente | CBM Total China | Qty Box China
     */
    public function downloadPlantillas(Request $request, $idContenedor)
    {
        try {
            // Agregado por cotización desde proveedores (sumas por id_cotizacion)
            $proveedoresAgg = DB::table('contenedor_consolidado_cotizacion_proveedores as CP')
                ->select(
                    'CP.id_cotizacion',
                    DB::raw('SUM(COALESCE(CP.cbm_total_china, CP.cbm_total, 0)) as sum_cbm_china'),
                    DB::raw('SUM(COALESCE(CP.qty_box_china, CP.qty_box, 0)) as sum_qty_box')
                )
                ->where('CP.id_contenedor', $idContenedor)
                ->groupBy('CP.id_cotizacion');

            $query = DB::table('contenedor_consolidado_cotizacion as CC')
                ->leftJoinSub($proveedoresAgg, 'CPA', function ($join) {
                    $join->on('CPA.id_cotizacion', '=', 'CC.id');
                })
                ->where('CC.id_contenedor', $idContenedor)
                ->whereNotNull('CC.estado_cliente')
                ->whereNull('CC.id_cliente_importacion')
                ->where('CC.estado_cotizador', 'CONFIRMADO')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('contenedor_consolidado_cotizacion_proveedores')
                        ->whereColumn('contenedor_consolidado_cotizacion_proveedores.id_cotizacion', 'CC.id');
                })
                ->select([
                    'CC.id as id_cotizacion',
                    'CC.nombre as cliente',
                    'CC.telefono as telefono',
                    DB::raw('COALESCE(CPA.sum_cbm_china, 0) as cbm_total_china'),
                    DB::raw('COALESCE(CPA.sum_qty_box, 0) as qty_box_china')
                ])
                ->orderBy('CC.nombre', 'asc');

            // Debug logging to help diagnose empty resultsets
            try {
                Log::info('downloadPlantillas SQL: ' . $query->toSql());
                Log::info('downloadPlantillas bindings: ' . json_encode($query->getBindings()));
            } catch (\Exception $e) {
                // ignore if driver doesn't support toSql in this context
            }

            $rowsQuery = $query->get();
            Log::info('downloadPlantillas rows count: ' . $rowsQuery->count());
            if ($rowsQuery->count() > 0) {
                Log::info('downloadPlantillas sample rows: ' . substr(json_encode($rowsQuery->take(5)), 0, 1000));
            }

            $rows = [];
            foreach ($rowsQuery as $r) {
                // Formatear: CBM con 2 decimales, Qty como entero
                $cbm = is_null($r->cbm_total_china) ? 0 : (float)$r->cbm_total_china;
                $qty = is_null($r->qty_box_china) ? 0 : (int)$r->qty_box_china;

                $rows[] = [
                    (string)($r->cliente ?? ''),
                    // Añadir unidad ' CBM' al valor de CBM
                    number_format($cbm, 2, '.', '') . ' CBM',
                    // Añadir unidad ' CAJAS' al quantity
                    (string)$qty . ' CAJAS',
                ];
            }

            $contenedor = DB::table('carga_consolidada_contenedor')->where('id', $idContenedor)->first();
            $suffix = $contenedor ? $contenedor->carga : $idContenedor;
            $timestamp = date('Y-m-d_H-i-s');
            $excelName = 'ROTULADO_PARED_' . $suffix . '_' . $timestamp . '.xlsx';

            // Create temp directory
            $tempDir = storage_path('app/temp/rotulado_' . $suffix . '_' . $timestamp);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Store the ROTULADO Excel file into temp dir
            $excelPath = 'temp/rotulado_' . $suffix . '_' . $timestamp . '/' . $excelName;
            $stored = Excel::store(new RotuladoParedExport($rows), $excelPath); // stores to storage/app/<path>
            $fullExcelPath = storage_path('app/' . $excelPath);

            // Also generate the FORMATO RESUMEN Excel and store in the same temp dir
            $rowsFormato = [];
            $rowsFormato[] = ['N°', 'CLIENTE', 'CELULAR', 'CBM TOTAL', 'TOTAL CAJAS', 'OBSERVACION', '# DE GUIA', 'FIRMA'];
            $idx = 1;
            foreach ($rowsQuery as $r) {
                $cbm = is_null($r->cbm_total_china) ? 0 : (float)$r->cbm_total_china;
                $qty = is_null($r->qty_box_china) ? 0 : (int)$r->qty_box_china;
                $rowsFormato[] = [
                    $idx++,
                    (string)($r->cliente ?? ''),
                    $this->formatPhoneNumber($r->telefono ?? ''),
                    // append unit labels like in RotuladoPared
                    number_format($cbm, 2, '.', '') . ' CBM',
                    (string)$qty . ' CAJAS',
                    '',
                    '',
                    ''
                ];
            }

            $excelName2 = 'FORMATO_RESUMEN_' . $suffix . '_' . $timestamp . '.xlsx';
            $excelPath2 = 'temp/rotulado_' . $suffix . '_' . $timestamp . '/' . $excelName2;
            // store the formato resumen
            $stored2 = Excel::store(new FormatoResumenExport($rowsFormato), $excelPath2);
            $fullExcelPath2 = storage_path('app/' . $excelPath2);

            // Generate PDFs per cotizacion and add to temp dir
            $pdfFiles = [];

            // Guard: ensure Dompdf is available
            if (!class_exists(\Dompdf\Dompdf::class)) {
                Log::warning('Dompdf not available, skipping PDF generation.');
            } else {
                foreach ($rowsQuery as $r) {
                    try {
                        // Sanitize cliente nombre for filename: ASCII, underscores, max length
                        $rawName = $r->cliente ?? ('cotizacion_' . $r->id_cotizacion);
                        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT', $rawName);
                        if ($trans === false) $trans = $rawName;
                        $trans = str_replace(' ', '_', $trans);
                        $trans = preg_replace('/[^A-Za-z0-9_\-]/', '', $trans);
                        $trans = strtoupper(substr($trans, 0, 40));
                        if (empty($trans)) {
                            $trans = 'COTIZACION_' . $r->id_cotizacion;
                        }

                        $pdfName = 'CARGO_ENTREGA_' . $trans . '_' . $suffix . '.pdf';
                        $pdfPath = $tempDir . DIRECTORY_SEPARATOR . $pdfName;

                        // Render Blade view
                        $html = view('entrega.cargo_entrega', [
                            'cliente' => $r->cliente,
                            'cotizacion_id' => $r->id_cotizacion,
                            'qty' => (int)($r->qty_box_china ?? 0),
                            'carga' => $suffix,
                        ])->render();

                        $options = new Options();
                        $options->set('isRemoteEnabled', true);
                        $options->set('defaultFont', 'DejaVu Sans');
                        $dompdf = new Dompdf($options);
                        $dompdf->loadHtml($html);
                        $dompdf->setPaper('A4', 'landscape');
                        $dompdf->render();
                        $output = $dompdf->output();
                        file_put_contents($pdfPath, $output);
                        $pdfFiles[] = $pdfPath;
                    } catch (\Exception $e) {
                        Log::error('Error generating PDF for cotizacion ' . $r->id_cotizacion . ': ' . $e->getMessage());
                        // continue generating others
                    }
                }
            }

            // Create ZIP with Excel + PDFs
            $zipName = 'PLANTILLAS_' . $suffix . '_' . $timestamp . '.zip';
            $zipPath = $tempDir . DIRECTORY_SEPARATOR . $zipName;
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                // add excel files
                if (file_exists($fullExcelPath)) {
                    $zip->addFile($fullExcelPath, $excelName);
                }
                if (isset($fullExcelPath2) && file_exists($fullExcelPath2)) {
                    $zip->addFile($fullExcelPath2, $excelName2);
                }
                // add pdfs
                foreach ($pdfFiles as $pf) {
                    if (file_exists($pf)) {
                        $zip->addFile($pf, basename($pf));
                    }
                }
                $zip->close();
                // Cleanup individual files (we keep only the ZIP to return)
                try {
                    if (file_exists($fullExcelPath)) {
                        @unlink($fullExcelPath);
                    }
                    if (isset($fullExcelPath2) && file_exists($fullExcelPath2)) {
                        @unlink($fullExcelPath2);
                    }
                    foreach ($pdfFiles as $pf) {
                        if (file_exists($pf)) {
                            @unlink($pf);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Error cleaning up temp files: ' . $e->getMessage());
                }
            } else {
                Log::error('Could not create ZIP at ' . $zipPath);
                return response()->json(['success' => false, 'message' => 'Error creando ZIP'], 500);
            }

            // Return ZIP for download and delete after send
            return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('downloadPlantillas error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error generando ROTULADO PARED', 'error' => $e->getMessage()], 500);
        }
    }

    public function getAllDelivery(Request $request)
    {
        // Lo obtiene de la tabla de clientes asociados al contenedor
        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->join('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
            ->leftJoin('consolidado_delivery_form_lima as L', 'L.id_cotizacion', '=', 'CC.id')
            ->leftJoin('consolidado_delivery_form_province as P', 'P.id_cotizacion', '=', 'CC.id')
            ->leftJoin('distrito as DIST', 'DIST.ID_Distrito', '=', 'P.id_district')
            ->select(
                'C.*',
                'CC.*',
                DB::raw("CASE 
                    WHEN L.id IS NOT NULL THEN 'LIMA'
                    WHEN P.id IS NOT NULL THEN 'PROVINCIA'
                    ELSE 'N/A'
                END as entrega"),
                // Si L.id no es nulo, usar final_destination_district, si no, usar id_district con join a distrito
                DB::raw("CASE WHEN L.id IS NOT NULL THEN L.final_destination_district ELSE DIST.No_Distrito END as ciudad"),
                // Para documento: usar r_doc para provincia y driver_doc para lima
                DB::raw("CASE WHEN P.id IS NOT NULL THEN P.r_doc WHEN L.id IS NOT NULL THEN L.driver_doc ELSE NULL END as documento"),
                // Para razón social: usar r_name para provincia y driver_name para lima
                DB::raw("CASE WHEN P.id IS NOT NULL THEN P.r_name WHEN L.id IS NOT NULL THEN L.drver_name ELSE NULL END as razon_social"),
                // Subquery para obtener pagos de DELIVERY
                DB::raw("CC.total_pago_delivery as importe"),
                DB::raw("(
                    SELECT IFNULL(SUM(cccp.monto), 0)
                    FROM {$this->table_contenedor_consolidado_cotizacion_coordinacion_pagos} cccp
                    JOIN {$this->table_pagos_concept} ccp ON cccp.id_concept = ccp.id
                    WHERE cccp.id_cotizacion = CC.id
                    AND ccp.name = 'DELIVERY'
                ) AS pagado"),
                // Subquery para obtener detalles de pagos de DELIVERY
                DB::raw("(
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id_pago', cccp.id,
                            'monto', cccp.monto,
                            'payment_date', cccp.payment_date,
                            'status', cccp.status,
                            'banco', cccp.banco,
                            'is_confirmed', cccp.is_confirmed,
                            'voucher_url', cccp.voucher_url
                        )
                    )
                    FROM {$this->table_contenedor_consolidado_cotizacion_coordinacion_pagos} cccp
                    JOIN {$this->table_pagos_concept} ccp ON cccp.id_concept = ccp.id
                    WHERE cccp.id_cotizacion = CC.id
                    AND ccp.name = 'DELIVERY'
                    ORDER BY cccp.payment_date ASC, cccp.id ASC
                ) AS pagos_details"),
                // Campo calculado para el estado de pago
                DB::raw("CASE 
                    WHEN (
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM {$this->table_contenedor_consolidado_cotizacion_coordinacion_pagos} cccp
                        JOIN {$this->table_pagos_concept} ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = CC.id
                        AND ccp.name = 'DELIVERY'
                    ) = 0 THEN 'PENDIENTE'
                    WHEN (
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM {$this->table_contenedor_consolidado_cotizacion_coordinacion_pagos} cccp
                        JOIN {$this->table_pagos_concept} ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = CC.id
                        AND ccp.name = 'DELIVERY'
                    ) < CC.total_pago_delivery THEN 'ADELANTO'
                    WHEN (
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM {$this->table_contenedor_consolidado_cotizacion_coordinacion_pagos} cccp
                        JOIN {$this->table_pagos_concept} ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = CC.id
                        AND ccp.name = 'DELIVERY'
                    ) = CC.total_pago_delivery THEN 'PAGADO'
                    ELSE 'SOBREPAGO'
                END as estado_pago")
            )

            ->whereNotNull('CC.estado_cliente')
            //where has pagos delivery
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from("{$this->table_contenedor_consolidado_cotizacion_coordinacion_pagos} as cccp")
                    ->join("{$this->table_pagos_concept} as ccp", 'cccp.id_concept', '=', 'ccp.id')
                    ->whereColumn('cccp.id_cotizacion', 'CC.id')
                    ->where('ccp.name', 'DELIVERY');
            })
            ->whereNull('CC.id_cliente_importacion')
            ->where('CC.estado_cotizador', 'CONFIRMADO');


        // Aplicar filtros adicionales si se proporcionan
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('CC.nombre', 'LIKE', "%{$search}%")
                    ->orWhere('CC.documento', 'LIKE', "%{$search}%")
                    ->orWhere('CC.correo', 'LIKE', "%{$search}%");
            });
        }

        // Filtro por estado (PENDIENTE/ADELANTO/PAGADO/SOBREPAGO)
        if ($request->has('estado') && $request->estado) {
            $estado = $request->estado;
            if ($estado === 'PENDIENTE') {
                $query->havingRaw('pagado = 0');
            } elseif ($estado === 'ADELANTO') {
                $query->havingRaw('pagado > 0 AND pagado < importe');
            } elseif ($estado === 'PAGADO') {
                $query->havingRaw('pagado = importe');
            } elseif ($estado === 'SOBREPAGO') {
                $query->havingRaw('pagado > importe');
            }
        }

        // Filtro por tipo de entrega (LIMA/PROVINCIA)
        if ($request->has('entrega') && $request->entrega) {
            $entrega = $request->entrega;
            if ($entrega === 'LIMA') {
                $query->whereNotNull('L.id');
            } elseif ($entrega === 'PROVINCIA') {
                $query->whereNotNull('P.id');
            }
        }

        // Filtro por contenedor específico
        if ($request->has('carga') && $request->carga && $request->carga != 'todos') {
            $query->where('C.id', $request->carga);
        }

        // Filtro por rango de fechas
        if ($request->has('fecha_inicio') && $request->fecha_inicio) {
            $query->whereDate('CC.fecha', '>=', $request->fecha_inicio);
        }
        if ($request->has('fecha_fin') && $request->fecha_fin) {
            $query->whereDate('CC.fecha', '<=', $request->fecha_fin);
        }

        // Ordenamiento
        $sortField = $request->input('sort_by', 'CC.id');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortField, $sortOrder);

        // Paginación
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        $data = $query->paginate($perPage, ['*'], 'page', $page);
        Log::info('data: ' . json_encode($data));
        //foreach pago_details generate url
        foreach ($data->items() as $item) {
            $item->pagos_details = json_decode($item->pagos_details, true);
            if (is_array($item->pagos_details)) {
                foreach ($item->pagos_details as $key => $pago) {
                    if (isset($pago['voucher_url'])) {
                        $item->pagos_details[$key]['voucher_url'] = $this->generateImageUrl($pago['voucher_url']);
                    }
                }
            }
        }
        return response()->json([
            'data' => $data->items(),
            'success' => true,
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem()
            ],
            'cargas_disponibles' => $this->getCargasDisponibles()
        ]);
    }
    private function getCargasDisponibles()
    {
        try {
            $cargas = DB::table('carga_consolidada_contenedor as cc')
                ->select('cc.id', 'cc.carga')
                ->distinct()
                ->where('cc.empresa', '!=', '1')
                ->orderBy('cc.carga')
                ->get();
            //carga is string number order by number
            $cargas = $cargas->sortBy(function ($carga) {
                return (int) $carga->carga;
            });
            return $cargas->values()->map(function ($carga) {
                return ['value' => (int) $carga->id, 'label' => "Contenedor #" . $carga->carga];
            });
        } catch (\Exception $e) {
            Log::error('Error en getCargasDisponibles: ' . $e->getMessage());
            return collect();
        }
    }

    public function getDelivery(Request $request, $idContenedor)
    {
        // Lo obtiene de la tabla de clientes asociados al contenedor
        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->join('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
            ->leftJoin('consolidado_delivery_form_lima as L', 'L.id_cotizacion', '=', 'CC.id')
            ->leftJoin('consolidado_delivery_form_province as P', 'P.id_cotizacion', '=', 'CC.id')
            ->leftJoin('distrito as DIST', 'DIST.ID_Distrito', '=', 'P.id_district')
            ->select(
                'C.*',
                'CC.*',
                DB::raw("CASE 
                    WHEN L.id IS NOT NULL THEN 'LIMA'
                    WHEN P.id IS NOT NULL THEN 'PROVINCIA'
                    ELSE 'N/A'
                END as entrega"),
                // Si L.id no es nulo, usar final_destination_district, si no, usar id_district con join a distrito
                DB::raw("CASE WHEN L.id IS NOT NULL THEN L.final_destination_district ELSE DIST.No_Distrito END as ciudad"),
                // Para documento: usar r_doc para provincia y driver_doc para lima
                DB::raw("CASE WHEN P.id IS NOT NULL THEN P.r_doc WHEN L.id IS NOT NULL THEN L.driver_doc ELSE NULL END as documento"),
                // Para razón social: usar r_name para provincia y driver_name para lima
                DB::raw("CASE WHEN P.id IS NOT NULL THEN P.r_name WHEN L.id IS NOT NULL THEN L.drver_name ELSE NULL END as razon_social"),
                // Tipo de servicio (DELIVERY o MONTACARGA)
                DB::raw("COALESCE(CC.tipo_servicio, 'DELIVERY') as tipo_servicio"),
                // Subquery para obtener pagos de DELIVERY
                DB::raw("CC.total_pago_delivery as importe"),
                DB::raw("(
                    SELECT IFNULL(SUM(cccp.monto), 0)
                    FROM {$this->table_contenedor_consolidado_cotizacion_coordinacion_pagos} cccp
                    JOIN {$this->table_pagos_concept} ccp ON cccp.id_concept = ccp.id
                    WHERE cccp.id_cotizacion = CC.id
                    AND ccp.name = 'DELIVERY'
                ) AS pagado"),
                // Subquery para obtener detalles de pagos de DELIVERY
                DB::raw("(
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id_pago', cccp.id,
                            'monto', cccp.monto,
                            'payment_date', cccp.payment_date,
                            'status', cccp.status,
                            'banco', cccp.banco,
                            'is_confirmed', cccp.is_confirmed,
                            'voucher_url', cccp.voucher_url
                        )
                    )
                    FROM {$this->table_contenedor_consolidado_cotizacion_coordinacion_pagos} cccp
                    JOIN {$this->table_pagos_concept} ccp ON cccp.id_concept = ccp.id
                    WHERE cccp.id_cotizacion = CC.id
                    AND ccp.name = 'DELIVERY'
                    ORDER BY cccp.payment_date ASC, cccp.id ASC
                ) AS pagos_details")
            )

            ->where('CC.id_contenedor', $idContenedor)
            ->whereNotNull('CC.estado_cliente')
            ->whereNull('CC.id_cliente_importacion')
            ->where('CC.estado_cotizador', 'CONFIRMADO');
        // Solo filas que tengan algún formulario asociado

        // Aplicar filtros adicionales si se proporcionan
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('CC.nombre', 'LIKE', "%{$search}%")
                    ->orWhere('CC.documento', 'LIKE', "%{$search}%")
                    ->orWhere('CC.correo', 'LIKE', "%{$search}%");
            });
        }

        // Calcular total_importes antes de paginar (de los registros filtrados)
        $totalImportes = (clone $query)->sum('CC.total_pago_delivery');

        // Ordenamiento
        $sortField = $request->input('sort_by', 'CC.id');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortField, $sortOrder);

        // Paginación   
        $page = $request->input('currentPage', 1);
        $perPage = $request->input('itemsPerPage', 100);
        $data = $query->paginate($perPage, ['*'], 'page', $page);

        // Preparar headers
        $headers = [
            'total_importes' => $totalImportes ?? 0
        ];

        return response()->json([
            'data' => $data->items(),
            'headers' => $headers,
            'success' => true,
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem()
            ]
        ]);
    }
    public function getEntregasDetalle($idCotizacion)
    {
        // Subconsulta: sumar cbm y qty por id_cotizacion (puede haber múltiples proveedores por cotización)
        $proveedoresAgg = DB::table('contenedor_consolidado_cotizacion_proveedores as CP')
            ->select(
                'CP.id_cotizacion',
                DB::raw('SUM(COALESCE(CP.cbm_total_china, 0)) as sum_cbm_china'),
                DB::raw('SUM(COALESCE(CP.cbm_total, 0)) as sum_cbm_total'),
                DB::raw('SUM(COALESCE(CP.qty_box_china, CP.qty_box, 0)) as sum_qty_box')
            )
            ->where('CP.id_cotizacion', $idCotizacion)
            ->groupBy('CP.id_cotizacion');
        // Subconsulta de asignación de rango/fecha por cotización (última asignación)
        $asignacionAgg = DB::table('consolidado_user_range_delivery as UR')
            ->select(
                'UR.id_cotizacion',
                DB::raw('MAX(UR.id_date) as id_date'),
                DB::raw('MAX(UR.id_range_date) as id_range_date')
            )
            ->where('UR.id_cotizacion', $idCotizacion)
            ->groupBy('UR.id_cotizacion');

        $row = DB::table('contenedor_consolidado_cotizacion as CC')
            ->leftJoin('consolidado_delivery_form_lima as L', 'L.id_cotizacion', '=', 'CC.id')
            ->leftJoin('consolidado_delivery_form_province as P', 'P.id_cotizacion', '=', 'CC.id')
            // Usuarios
            ->leftJoin('users as UL', 'UL.id', '=', 'L.id_user')
            ->leftJoin('users as UP', 'UP.id', '=', 'P.id_user')
            // Geográficos (Provincia)
            ->leftJoin('departamento as DPT', 'DPT.ID_Departamento', '=', 'P.id_department')
            ->leftJoin('provincia as PROV', 'PROV.ID_Provincia', '=', 'P.id_province')
            ->leftJoin('distrito as DIST', 'DIST.ID_Distrito', '=', 'P.id_district')
            // Sumas de proveedores por cotización
            ->leftJoinSub($proveedoresAgg, 'CPA', function ($join) {
                $join->on('CPA.id_cotizacion', '=', 'CC.id');
            })
            // Asignación
            ->leftJoinSub($asignacionAgg, 'UR2', function ($join) {
                $join->on('UR2.id_cotizacion', '=', 'CC.id');
            })
            ->leftJoin('consolidado_delivery_date as D2', 'D2.id', '=', 'UR2.id_date')
            ->leftJoin('consolidado_delivery_range_date as R2', 'R2.id', '=', 'UR2.id_range_date')
            // Fallback de Lima (si asignación no existe, usar el rango de L)
            ->leftJoin('consolidado_delivery_range_date as RL', 'RL.id', '=', 'L.id_range_date')
            ->leftJoin('consolidado_delivery_date as DL', 'DL.id', '=', 'RL.id_date')
            ->where('CC.id', $idCotizacion)
            ->select([
                'CC.*',
                'CC.id as cotizacion_id',
                'CC.id_contenedor',
                // IDs de los formularios
                'L.id as lima_id',
                'P.id as province_id',
                // Determinación de tipo de formulario (0 Provincia / 1 Lima)
                DB::raw('CASE WHEN P.id IS NOT NULL THEN 0 WHEN L.id IS NOT NULL THEN 1 ELSE NULL END as type_form'),
                // Usuario
                DB::raw('CASE WHEN P.id IS NOT NULL THEN P.id_user WHEN L.id IS NOT NULL THEN L.id_user ELSE NULL END as form_user_id'),
                DB::raw('CASE WHEN P.id IS NOT NULL THEN UP.name WHEN L.id IS NOT NULL THEN UL.name ELSE NULL END as form_user_name'),
                DB::raw('CASE WHEN P.id IS NOT NULL THEN UP.email WHEN L.id IS NOT NULL THEN UL.email ELSE NULL END as form_user_email'),
                // Datos de entrega (preferir UR2; fallback a DL/RL si no existe asignación)
                DB::raw("CASE 
                            WHEN UR2.id_date IS NOT NULL THEN CONCAT(D2.year, '-', LPAD(D2.month, 2, '0'), '-', LPAD(D2.day, 2, '0'))
                            WHEN DL.id IS NOT NULL THEN CONCAT(DL.year, '-', LPAD(DL.month, 2, '0'), '-', LPAD(DL.day, 2, '0'))
                            ELSE NULL END as delivery_date"),
                DB::raw("CASE WHEN R2.id IS NOT NULL THEN R2.start_time WHEN RL.id IS NOT NULL THEN RL.start_time ELSE NULL END as delivery_start_time"),
                DB::raw("CASE WHEN R2.id IS NOT NULL THEN R2.end_time WHEN RL.id IS NOT NULL THEN RL.end_time ELSE NULL END as delivery_end_time"),
                DB::raw('COALESCE(UR2.id_date, DL.id) as delivery_date_id'),
                DB::raw('COALESCE(UR2.id_range_date, RL.id) as delivery_range_id'),
                // Agregados por cotización desde proveedores
                DB::raw('COALESCE(CPA.sum_cbm_china, 0) as cbm_total_china'),
                DB::raw('COALESCE(CPA.sum_qty_box, 0) as qty_box_china'),

                // Campos LIMA
                'L.pick_name',
                'L.pick_doc',
                'L.import_name as lima_import_name',
                'L.productos',
                'L.voucher_doc as lima_voucher_doc',
                'L.voucher_doc_type as lima_voucher_doc_type',
                'L.voucher_name as lima_voucher_name',
                'L.voucher_email as lima_voucher_email',
                'L.drver_name',
                'L.driver_doc_type',
                'L.driver_doc',
                'L.driver_license',
                'L.driver_plate',
                'L.final_destination_place',
                'L.final_destination_district',

                // Campos PROVINCIA
                'P.importer_nmae as province_import_name',
                'P.productos as province_productos',
                'P.voucher_doc as province_voucher_doc',
                'P.voucher_doc_type as province_voucher_doc_type',
                'P.voucher_name as province_voucher_name',
                'P.voucher_email as province_voucher_email',
                'P.id_agency',
                'P.agency_ruc',
                'P.agency_name',
                'P.r_type',
                'P.r_doc',
                'P.r_name',
                'P.r_phone',
                'P.id_department',
                'P.id_province',
                'P.id_district',
                'P.agency_address_initial_delivery',
                'P.agency_address_final_delivery',
                'P.home_adress_delivery',
                'DPT.No_Departamento as department_name',
                'PROV.No_Provincia as province_name',
                'DIST.No_Distrito as district_name',
            ])
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Detalle no encontrado'], 404);
        }

        $typeForm = isset($row->type_form) ? (int) $row->type_form : null;
        $delivery = [
            'date' => $row->delivery_date,
            'start_time' => $row->delivery_start_time,
            'end_time' => $row->delivery_end_time,
            'date_id' => $row->delivery_date_id ? (int)$row->delivery_date_id : null,
            'range_id' => $row->delivery_range_id ? (int)$row->delivery_range_id : null,
        ];

        $formUser = [
            'id' => $row->form_user_id ? (int)$row->form_user_id : null,
            'name' => $row->form_user_name,
            'email' => $row->form_user_email,
        ];

        $payload = [
            'cotizacion_id' => (int) $row->cotizacion_id,
            'peso' => (float) $row->peso,
            'id_contenedor' => (int) $row->id_contenedor,
            'type_form' => $typeForm, // 0=Provincia, 1=Lima
            'delivery' => $delivery,
            'form_user' => $formUser,
            'cbm_total_china' => (float) $row->cbm_total_china,
            'qty_box_china' => (int) $row->qty_box_china,
        ];

        // Conformidad (fotos): obtener por cotización y tipo de formulario usando las nuevas tablas
        $payload['conformidad'] = [];
        if ($typeForm !== null) {
            // type_form = 0 es Lima, type_form = 1 es Provincia
            $tableName = $typeForm === 1 ? 'consolidado_delivery_form_lima_conformidad' : 'consolidado_delivery_form_province_conformidad';
            $formIdField = $typeForm === 1 ? 'consolidado_delivery_form_lima_id' : 'consolidado_delivery_form_province_id';

            // Obtener el ID del formulario correspondiente
            $formId = $typeForm === 1 ? $row->lima_id ?? null : $row->province_id ?? null;

            if ($formId) {
                $confPhotos = DB::table($tableName)
                    ->where('id_cotizacion', $idCotizacion)
                    ->where('id_contenedor', $row->id_contenedor)
                    ->orderByDesc('created_at')
                    ->get();

                $payload['conformidad'] = $confPhotos->map(function ($photo) {
                    return [
                        'id' => (int) $photo->id,
                        'file_path' => $photo->file_path,
                        // Usar helper centralizado para construir la URL pública
                        'file_url' => $this->generateImageUrl($photo->file_path),
                        'file_type' => $photo->file_type,
                        'file_size' => $photo->file_size ? (int)$photo->file_size : null,
                        'file_original_name' => $photo->file_original_name,
                        'created_at' => $photo->created_at,
                    ];
                })->toArray();
            }
        }

        if ($typeForm === 1) { // Lima
            $payload['lima'] = [
                'pick_name' => $row->pick_name,
                'pick_doc' => $row->pick_doc,
                'import_name' => $row->lima_import_name,
                'productos' => $row->productos,
                'voucher_doc' => $row->lima_voucher_doc,
                'voucher_doc_type' => $row->lima_voucher_doc_type,
                'voucher_name' => $row->lima_voucher_name,
                'voucher_email' => $row->lima_voucher_email,
                'drver_name' => $row->drver_name,
                'driver_doc_type' => $row->driver_doc_type,
                'driver_doc' => $row->driver_doc,
                'driver_license' => $row->driver_license,
                'driver_plate' => $row->driver_plate,
                'final_destination_place' => $row->final_destination_place,
                'final_destination_district' => $row->final_destination_district,
            ];
        } elseif ($typeForm === 0) { // Provincia
            $payload['province'] = [
                'importer_nmae' => $row->province_import_name,
                'productos' => $row->province_productos,
                'voucher_doc' => $row->province_voucher_doc,
                'voucher_doc_type' => $row->province_voucher_doc_type,
                'voucher_name' => $row->province_voucher_name,
                'voucher_email' => $row->province_voucher_email,
                'id_agency' => $row->id_agency ? (int)$row->id_agency : null,
                'agency_ruc' => $row->agency_ruc,
                'agency_name' => $row->agency_name,
                'r_type' => $row->r_type,
                'r_doc' => $row->r_doc,
                'r_name' => $row->r_name,
                'r_phone' => $row->r_phone,
                'id_department' => $row->id_department ? (int)$row->id_department : null,
                'id_province' => $row->id_province ? (int)$row->id_province : null,
                'id_district' => $row->id_district ? (int)$row->id_district : null,
                'department_name' => $row->department_name,
                'province_name' => $row->province_name,
                'district_name' => $row->district_name,
                'agency_address_initial_delivery' => $row->agency_address_initial_delivery,
                'agency_address_final_delivery' => $row->agency_address_final_delivery,
                'home_adress_delivery' => $row->home_adress_delivery,
            ];
        } else {
            // Sin formulario: devolver vacío pero consistente
            $payload['lima'] = null;
            $payload['province'] = null;
        }

        return response()->json(['data' => $payload, 'success' => true]);
    }

    /**
     * Guarda 2 fotos de conformidad de entrega (para Lima o Provincia).
     * Body (multipart/form-data):
     *  - type_form: 0 (Provincia) | 1 (Lima)
     *  - id_contenedor, id_cotizacion
     *  - photo_1, photo_2 (files)
     *  - optional: id_form_lima | id_form_province (si ya lo tienes)
     */
    public function uploadConformidad(Request $request)
    {
        try {
            $request->validate([
                'id_contenedor' => 'required|integer',
                'id_cotizacion' => 'required|integer',
                'type_form' => 'required|in:0,1',
                'photo_1' => 'sometimes|image|max:8192',
                'photo_2' => 'sometimes|image|max:8192',
            ]);

            // Requiere al menos una imagen
            if (!$request->hasFile('photo_1') && !$request->hasFile('photo_2')) {
                return response()->json([
                    'message' => 'Debe enviar al menos una imagen (photo_1 o photo_2) para guardar la conformidad',
                    'success' => false
                ], 422);
            }

            $typeForm = (int) $request->input('type_form');
            $idContenedor = (int) $request->input('id_contenedor');
            $idCotizacion = (int) $request->input('id_cotizacion');

            // Determinar tabla y campo según el tipo de formulario
            // type_form: 0 = Provincia, 1 = Lima
            $tableName = $typeForm === 1 ? 'consolidado_delivery_form_lima_conformidad' : 'consolidado_delivery_form_province_conformidad';
            $formTableName = $typeForm === 1 ? 'consolidado_delivery_form_lima' : 'consolidado_delivery_form_province';
            $formIdField = $typeForm === 1 ? 'consolidado_delivery_form_lima_id' : 'consolidado_delivery_form_province_id';

            // Obtener el ID del formulario correspondiente
            $formId = DB::table($formTableName)
                ->where('id_cotizacion', $idCotizacion)
                ->where('id_contenedor', $idContenedor)
                ->value('id');

            if (!$formId) {
                return response()->json([
                    'message' => 'No se encontró el formulario de delivery correspondiente',
                    'success' => false
                ], 404);
            }

            // Definir disco y carpeta
            $disk = 'public';
            $folder = 'delivery_conformidad/' . $idCotizacion;

            DB::beginTransaction();
            try {
                $inserted = [];
                $photo1Path = null;
                $photo1MimeType = null;
                $photo2Path = null;
                $photo2MimeType = null;

                // Procesar photo_1 si existe
                if ($request->hasFile('photo_1')) {
                    $file = $request->file('photo_1');
                    $filename = time() . '_1_' . $file->getClientOriginalName();
                    $storedPath = $file->storeAs($folder, $filename, $disk);
                    $photo1Path = storage_path('app/public/' . $storedPath);
                    $photo1MimeType = $file->getClientMimeType();

                    $insert = [
                        $formIdField => $formId,
                        'id_cotizacion' => $idCotizacion,
                        'id_contenedor' => $idContenedor,
                        'file_path' => $storedPath,
                        'file_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'file_original_name' => $file->getClientOriginalName(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $newId = DB::table($tableName)->insertGetId($insert);
                    $inserted[] = $newId;
                }

                // Procesar photo_2 si existe
                if ($request->hasFile('photo_2')) {
                    $file = $request->file('photo_2');
                    $filename = time() . '_2_' . $file->getClientOriginalName();
                    $storedPath = $file->storeAs($folder, $filename, $disk);
                    $photo2Path = storage_path('app/public/' . $storedPath);
                    $photo2MimeType = $file->getClientMimeType();

                    $insert = [
                        $formIdField => $formId,
                        'id_cotizacion' => $idCotizacion,
                        'id_contenedor' => $idContenedor,
                        'file_path' => $storedPath,
                        'file_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'file_original_name' => $file->getClientOriginalName(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $newId = DB::table($tableName)->insertGetId($insert);
                    $inserted[] = $newId;
                }
                DB::commit();
                $cotizacion = Cotizacion::find($idCotizacion);
                $nombre = $cotizacion->nombre;
                $numeroWhatsapp = $cotizacion->telefono;
                //remvoe empty spaces and special characters
                $numeroWhatsapp = preg_replace('/[^0-9]/', '', $numeroWhatsapp);
                if (strlen($numeroWhatsapp) < 9) {
                    $numeroWhatsapp = '51' . $numeroWhatsapp;
                }
                $numeroWhatsapp = $numeroWhatsapp . '@c.us';
                $contenedor = Contenedor::find($idContenedor);
                $carga = $contenedor->carga;
                $message = "Hola $nombre 👋
Adjunto el sustento de entrega correspondiente a su importación del consolidado #$carga.\n
Muchas gracias por confiar en Pro Business. Si tiene una próxima importación, estaremos encantados de ayudarlo nuevamente. No dude en escribirnos ✈️📦";
                $this->sendMessage($message, $numeroWhatsapp);
                if ($photo1Path) {
                    $this->sendMedia($photo1Path, $photo1MimeType, null, $numeroWhatsapp);
                }
                if ($photo2Path) {
                    $this->sendMedia($photo2Path, $photo2MimeType, null, $numeroWhatsapp);
                }

                return response()->json([
                    'message' => 'Fotos de conformidad subidas correctamente',
                    'inserted_ids' => $inserted,
                    'count' => count($inserted),
                    'success' => true
                ], 201);
            } catch (\Throwable $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Error al guardar la conformidad: ' . $e->getMessage(),
                    'success' => false
                ], 500);
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al guardar la conformidad: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    // Alias para subir conformidad usando el id de cotización en la ruta
    public function uploadConformidadForCotizacion(Request $request, $idCotizacion)
    {
        // Inyectar id_cotizacion desde la ruta al request
        $request->merge(['id_cotizacion' => (int)$idCotizacion]);
        return $this->uploadConformidad($request);
    }

    /**
     * Obtiene las fotos de conformidad para una cotización y tipo de formulario.
     * Query: ?type_form=0|1
     */
    public function getConformidad(Request $request, $idCotizacion)
    {
        $typeForm = (int) $request->query('type_form', 1);
        $row = DB::table('consolidado_delivery_conformidad')
            ->where('id_cotizacion', $idCotizacion)
            ->where('type_form', $typeForm)
            ->orderByDesc('id')
            ->first();
        if (!$row) {
            return response()->json(['data' => null, 'success' => true]);
        }
        return response()->json([
            'data' => [
                'id' => (int)$row->id,
                'photo_1' => $row->photo_1_path,
                'photo_2' => $row->photo_2_path,
                // URLs públicas usando el helper unificado
                'photo_1_url' => $this->generateImageUrl($row->photo_1_path ?? ''),
                'photo_2_url' => $this->generateImageUrl($row->photo_2_path ?? ''),
                'uploaded_by' => $row->uploaded_by,
                'uploaded_at' => $row->uploaded_at,
            ],
            'success' => true
        ]);
    }

    /**
     * Actualiza (reemplaza) una o ambas fotos de conformidad.
     * Acepta photo_1 y/o photo_2 como archivos image.
     */
    public function updateConformidad(Request $request, $id)
    {
        $request->validate([
            'photo_1' => 'nullable|image|max:8192',
            'photo_2' => 'nullable|image|max:8192',
        ]);

        if (!$request->hasFile('photo_1') && !$request->hasFile('photo_2')) {
            return response()->json(['message' => 'Debe enviar al menos una imagen para actualizar', 'success' => false], 422);
        }

        $row = DB::table('consolidado_delivery_conformidad')->where('id', $id)->first();
        if (!$row) {
            return response()->json(['message' => 'Conformidad no encontrada', 'success' => false], 404);
        }

        $disk = config('filesystems.default', 'public');
        $folder = 'delivery_conformidad/' . $row->id_cotizacion;
        $update = ['updated_at' => now(), 'uploaded_by' => auth()->id(), 'uploaded_at' => now()];

        if ($request->hasFile('photo_1')) {
            // Eliminar archivo anterior si existe
            if (!empty($row->photo_1_path)) {
                try {
                    Storage::disk($disk)->delete($row->photo_1_path);
                } catch (\Throwable $e) { /* ignore */
                }
            }
            $p1 = $request->file('photo_1');
            $path1 = $p1->store($folder, $disk);
            $update['photo_1_path'] = $path1;
            $update['photo_1_mime'] = $p1->getClientMimeType();
            $update['photo_1_size'] = $p1->getSize();
        }

        if ($request->hasFile('photo_2')) {
            if (!empty($row->photo_2_path)) {
                try {
                    Storage::disk($disk)->delete($row->photo_2_path);
                } catch (\Throwable $e) { /* ignore */
                }
            }
            $p2 = $request->file('photo_2');
            $path2 = $p2->store($folder, $disk);
            $update['photo_2_path'] = $path2;
            $update['photo_2_mime'] = $p2->getClientMimeType();
            $update['photo_2_size'] = $p2->getSize();
        }

        DB::table('consolidado_delivery_conformidad')->where('id', $id)->update($update);

        $refreshed = DB::table('consolidado_delivery_conformidad')->where('id', $id)->first();
        return response()->json([
            'data' => [
                'id' => (int)$refreshed->id,
                'photo_1' => $refreshed->photo_1_path,
                'photo_2' => $refreshed->photo_2_path,
                'uploaded_by' => $refreshed->uploaded_by,
                'uploaded_at' => $refreshed->uploaded_at,
            ],
            'success' => true
        ]);
    }

    /**
     * Elimina un registro de conformidad y sus archivos asociados.
     */
    public function deleteConformidad(Request $request, $id)
    {
        $request->validate([
            'type_form' => 'required|in:0,1',
        ]);

        $typeForm = (int) $request->input('type_form');

        // Determinar tabla según el tipo de formulario
        // type_form = 0 es Lima, type_form = 1 es Provincia
        $tableName = $typeForm === 1 ? 'consolidado_delivery_form_lima_conformidad' : 'consolidado_delivery_form_province_conformidad';

        $row = DB::table($tableName)->where('id', $id)->first();
        if (!$row) {
            return response()->json(['message' => 'Conformidad no encontrada', 'success' => false], 404);
        }

        $disk = config('filesystems.default', 'public');

        // Eliminar archivo físico
        try {
            if (!empty($row->file_path)) {
                Storage::disk($disk)->delete($row->file_path);
            }
        } catch (\Throwable $e) {
            // Ignorar errores de eliminación de archivos
        }

        // Eliminar registro de la base de datos
        DB::table($tableName)->where('id', $id)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Actualiza los datos del detalle de entrega (formulario Lima o Provincia) según type_form.
     * Body: type_form=0|1 y solo los campos permitidos.
     *
     * Restricciones solicitadas:
     *  - Lima (type_form=1): solo datos del conductor y destinos, además de comprobante.
     *  - Provincia (type_form=0): datos de facturación (r_* e import_name), de agencia (id_agency/agency_* y direcciones) y de comprobante.
     *  - Comprobante aplica para ambas tablas: voucher_doc, voucher_doc_type, voucher_name, voucher_email.
     */
    public function updateEntregasDetalle(Request $request, $idCotizacion)
    {
        $request->validate([
            'type_form' => 'required|in:0,1',
        ]);
        $typeForm = (int) $request->input('type_form');

        if ($typeForm === 1) { // Lima
            $rules = [
                'productos' => 'sometimes|string',
                'voucher_doc' => 'sometimes|string',
                'voucher_doc_type' => 'sometimes|in:BOLETA,FACTURA',
                'voucher_name' => 'sometimes|string',
                'voucher_email' => 'sometimes',
                'drver_name' => 'sometimes|string',
                'driver_doc_type' => 'sometimes|in:DNI,PASAPORTE',
                'driver_doc' => 'sometimes|string',
                'driver_license' => 'sometimes|string',
                'driver_plate' => 'sometimes|string',
                'final_destination_place' => 'sometimes|string',
                'final_destination_district' => 'sometimes|integer',
            ];
            $data = $request->validate($rules);

            $updated = DB::table('consolidado_delivery_form_lima')
                ->where('id_cotizacion', $idCotizacion)
                ->orderByDesc('id')
                ->limit(1)
                ->update(array_merge($data, ['updated_at' => now()]));
            if (!$updated) {
                return response()->json(['message' => 'Formulario Lima no encontrado para esta cotización', 'success' => false], 404);
            }
        } else { // Provincia
            $rules = [
                // Facturación
                'importer_nmae' => 'sometimes|string',
                'productos' => 'sometimes|string',
                'r_type' => 'sometimes|in:PERSONA NATURAL,EMPRESA',
                'r_doc' => 'sometimes|string',
                'r_name' => 'sometimes|string',
                'r_phone' => 'sometimes|string',
                // Ubigeo
                'id_department' => 'sometimes|integer|exists:departamento,ID_Departamento',
                'id_province' => 'sometimes|integer|exists:provincia,ID_Provincia',
                'id_district' => 'sometimes|integer|exists:distrito,ID_Distrito',
                // Agencia
                'id_agency' => 'sometimes|integer|exists:delivery_agencies,id',
                'agency_ruc' => 'sometimes|string',
                'agency_name' => 'sometimes|string',
                'agency_address_initial_delivery' => 'sometimes|string',
                'agency_address_final_delivery' => 'sometimes|string',
                // Comprobante (aplica a ambas tablas)
                'voucher_doc' => 'sometimes|string',
                'voucher_doc_type' => 'sometimes|in:BOLETA,FACTURA',
                'voucher_name' => 'sometimes|string',
                'voucher_email' => 'sometimes',
            ];
            $data = $request->validate($rules);
            $data['importer_nmae'] = $request->import_name ?? $request->r_name;
            //delet key import_name
            unset($data['import_name']);

            $updated = DB::table('consolidado_delivery_form_province')
                ->where('id_cotizacion', $idCotizacion)
                ->orderByDesc('id')
                ->limit(1)
                ->update(array_merge($data, ['updated_at' => now()]));
            if (!$updated) {
                return response()->json(['message' => 'Formulario Provincia no encontrado para esta cotización', 'success' => false], 404);
            }
        }

        return response()->json(['message' => 'Detalle actualizado correctamente', 'success' => true]);
    }

    /**
     * Elimina los datos del detalle de entrega (formulario Lima o Provincia) según type_form.
     * Si no se envía type_form, se intentará eliminar Lima y Provincia para la cotización.
     */
    public function deleteEntregasDetalle(Request $request, $idCotizacion)
    {
        $typeForm = $request->input('type_form');

        DB::beginTransaction();
        try {
            $deleted = 0;

            // 1) Eliminar detalle de formularios según type_form
            if ($typeForm === null) {
                $deleted += DB::table('consolidado_delivery_form_lima')->where('id_cotizacion', $idCotizacion)->delete();
                $deleted += DB::table('consolidado_delivery_form_province')->where('id_cotizacion', $idCotizacion)->delete();
            } elseif ((int)$typeForm === 1) {
                $deleted += DB::table('consolidado_delivery_form_lima')->where('id_cotizacion', $idCotizacion)->delete();
            } else {
                $deleted += DB::table('consolidado_delivery_form_province')->where('id_cotizacion', $idCotizacion)->delete();
            }

            // 2) Eliminar asignaciones de fecha/rango y sus fechas asociadas (1 a 1 por requerimiento)
            $assignments = DB::table('consolidado_user_range_delivery')
                ->where('id_cotizacion', $idCotizacion)
                ->get(['id_date', 'id_range_date']);

            if ($assignments->count() > 0) {
                // Borrar todas las asignaciones del usuario para esta cotización
                DB::table('consolidado_user_range_delivery')->where('id_cotizacion', $idCotizacion)->delete();
            }

            // 3) Actualizar la tabla contenedor_consolidado_cotizacion y hacer que delivery_form_registered_at sea NULL
            DB::table('contenedor_consolidado_cotizacion')
                ->where('id', $idCotizacion)
                ->update(['delivery_form_registered_at' => null]);

            if ($deleted === 0 && $assignments->count() === 0) {
                DB::rollBack();
                return response()->json(['message' => 'No se encontró detalle para eliminar', 'success' => false], 404);
            }

            DB::commit();
            return response()->json(['message' => 'Detalle eliminado correctamente', 'success' => true]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al eliminar el detalle: ' . $e->getMessage(), 'success' => false], 500);
        }
    }
    public function saveImporteDelivery(Request $request)
    {
        try {
            $idCotizacion = $request->id_cotizacion;
            $importe = $request->importe;
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['message' => 'Cotización no encontrada', 'success' => false]);
            }
            $cotizacion->total_pago_delivery = $importe;
            $cotizacion->save();
            return response()->json(['message' => 'Importe guardado correctamente', 'success' => true]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'success' => false]);
        }
    }
    public function saveServicioDelivery(Request $request)
    {
        try {
            $idCotizacion = $request->id_cotizacion;
            $tipoServicio = $request->tipo_servicio;
            
            // Validar que el tipo de servicio sea válido
            if (!in_array($tipoServicio, ['DELIVERY', 'MONTACARGA'])) {
                return response()->json(['message' => 'Tipo de servicio inválido. Debe ser DELIVERY o MONTACARGA', 'success' => false], 422);
            }
            
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['message' => 'Cotización no encontrada', 'success' => false], 404);
            }
            
            // Actualizar el campo tipo_servicio en la tabla
            DB::table('contenedor_consolidado_cotizacion')
                ->where('id', $idCotizacion)
                ->update(['tipo_servicio' => $tipoServicio]);
            
            return response()->json(['message' => 'Servicio guardado correctamente', 'success' => true]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'success' => false], 500);
        }
    }
    public function savePagosDelivery(Request $request)
    {
        try {
            Log::info('savePagosDelivery: ' . json_encode($request->all()));
            $idCotizacion = $request->idCotizacion;
            $idContenedor = $request->idContenedor;
            $monto = $request->monto;
            $banco = $request->banco;
            $fecha = $request->fecha;
            $voucher = $request->voucher;
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['message' => 'Cotización no encontrada', 'success' => false]);
            }
            DB::beginTransaction();
            //save voucher in storage
            $voucherUrl = Storage::disk('public')->put('vouchers', $voucher);
            $pago = new Pago();
            $pago->id_concept = PagoConcept::CONCEPT_PAGO_DELIVERY;
            $pago->id_cotizacion = $idCotizacion;
            $pago->id_contenedor = $idContenedor;
            $pago->monto = $monto;
            $pago->banco = $banco;
            $pago->payment_date = $fecha;
            $pago->voucher_url = $voucherUrl;
            $pago->save();
            DB::commit();
            return response()->json(['message' => 'Pagos guardados correctamente', 'success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage(), 'success' => false]);
        }
    }
    public function sendMessageDelivery(Request $request, $idCotizacion)
    {
        try {
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['message' => 'Cotización no encontrada', 'success' => false]);
            }

            $idContenedor = $cotizacion->id_contenedor;
            $contenedor = Contenedor::find($idContenedor);
            if (!$contenedor) {
                return response()->json(['message' => 'Contenedor no encontrada', 'success' => false]);
            }
            $urlClientes = env('APP_URL_CLIENTES');
            $urlProvincia = $urlClientes . '/formulario-entrega/provincia/' . $idContenedor;
            $urlLima = $urlClientes . '/formulario-entrega/lima/' . $idContenedor;
            $message = "Hola " . $cotizacion->nombre_cliente . ", somos de Pro Business y este mensaje es para informarte que estamos esperando a que llene el formulario para entregar tu pedido\n\n" .
                "del consolidado #" . $contenedor->carga . "\n\n" .
                "Link Provincia: " . $urlProvincia . "\n\n Link Lima: " . $urlLima;
            $telefono = preg_replace('/\s+/', '', $cotizacion->telefono);
            $this->phoneNumberId = $telefono ? $telefono . '@c.us' : '';
            $this->sendMessage($message);
            return response()->json(['message' => 'Mensaje enviado correctamente', 'success' => true]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'success' => false]);
        }
    }

    public function sendRecordatorioFormularioDelivery(Request $request, $idCotizacion)
    {
        try {
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['message' => 'Cotización no encontrada', 'success' => false], 404);
            }

            $idContenedor = $cotizacion->id_contenedor;
            $contenedor = Contenedor::find($idContenedor);
            if (!$contenedor) {
                return response()->json(['message' => 'Contenedor no encontrado', 'success' => false], 404);
            }

            $message = $request->input('message');
            if (!$message) {
                return response()->json(['message' => 'El mensaje es requerido', 'success' => false], 422);
            }

            $telefono = preg_replace('/\D+/', '', $cotizacion->telefono);
            if (strlen($telefono) < 9) {
                $telefono = '51' . $telefono;
            }
            $numeroWhatsapp = $telefono . '@c.us';

            $result = $this->sendMessage($message, $numeroWhatsapp);
            
            if ($result['status']) {
                return response()->json(['message' => 'Mensaje enviado correctamente', 'success' => true]);
            } else {
                return response()->json(['message' => 'Error al enviar mensaje: ' . ($result['response']['error'] ?? 'Error desconocido'), 'success' => false], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en sendRecordatorioFormularioDelivery: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage(), 'success' => false], 500);
        }
    }

    public function sendCobroCotizacionFinalDelivery(Request $request, $idCotizacion)
    {
        try {
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['message' => 'Cotización no encontrada', 'success' => false], 404);
            }

            $message = $request->input('message');
            if (!$message) {
                return response()->json(['message' => 'El mensaje es requerido', 'success' => false], 422);
            }

            $telefono = preg_replace('/\D+/', '', $cotizacion->telefono);
            if (strlen($telefono) < 9) {
                $telefono = '51' . $telefono;
            }
            $numeroWhatsapp = $telefono . '@c.us';

            $result = $this->sendMessage($message, $numeroWhatsapp);
            
            if ($result['status']) {
                return response()->json(['message' => 'Mensaje enviado correctamente', 'success' => true]);
            } else {
                return response()->json(['message' => 'Error al enviar mensaje: ' . ($result['response']['error'] ?? 'Error desconocido'), 'success' => false], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en sendCobroCotizacionFinalDelivery: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage(), 'success' => false], 500);
        }
    }

    public function sendCobroDeliveryDelivery(Request $request, $idCotizacion)
    {
        try {
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['message' => 'Cotización no encontrada', 'success' => false], 404);
            }

            /**message Hola #nombrecliente, por favor proceder con el pago del #servicio  .
            
Monto: ##

Envio nuestra cuenta bancaria.  */
            $message = "Hola " . $cotizacion->nombre . ", por favor proceder con el pago del " . $cotizacion->tipo_servicio . ".
Monto: " . $cotizacion->total_pago_delivery . "
Envio nuestra cuenta bancaria. ";

            $telefono = preg_replace('/\D+/', '', $cotizacion->telefono);
            if (strlen($telefono) < 9) {
                $telefono = '51' . $telefono;
            }
            $numeroWhatsapp = $telefono . '@c.us';

            $result = $this->sendMessage($message, $numeroWhatsapp);
            $pagosUrl = public_path('assets/images/pagos-full.jpg');

            $this->sendMedia($pagosUrl, 'image/jpg', null, $telefono, 15, 'consolidado', 'Numeros_de_cuenta.jpg');
            if ($result['status']) {
                return response()->json(['message' => 'Mensaje enviado correctamente', 'success' => true]);
            } else {
                return response()->json(['message' => 'Error al enviar mensaje: ' . ($result['response']['error'] ?? 'Error desconocido'), 'success' => false], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en sendCobroDeliveryDelivery: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage(), 'success' => false], 500);
        }
    }

    /**
     * Lista agencias de delivery para selects.
     * Tabla: delivery_agencies
     * Query params opcionales:
     * - search: texto a buscar (si existen columnas name/agency_name o ruc/agency_ruc)
     * - ids: lista separada por coma para filtrar por IDs específicos
     * - currentPage, itemsPerPage: paginación
     */
    public function getAgencias(Request $request)
    {
        $query = DB::table('delivery_agencies')->select('id', 'name', 'ruc');

        // Filtro por IDs específicos (ids=1,2,3)
        if ($request->filled('ids')) {
            $ids = array_filter(array_map('intval', explode(',', (string)$request->input('ids'))));
            if (!empty($ids)) {
                $query->whereIn('id', $ids);
            }
        }

        // Búsqueda por nombre o RUC
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('ruc', 'LIKE', "%{$search}%");
            });
        }

        // Orden por nombre, luego por id
        $query->orderBy('name', 'asc')->orderBy('id', 'asc');

        $page = (int) $request->input('currentPage', 1);
        $perPage = (int) $request->input('itemsPerPage', 100);
        $data = $query->paginate($perPage, ['*'], 'page', $page);

        // Formato para selects: { value, label, id, name, ruc }
        $items = array_map(function ($row) {
            return [
                'id' => (int) $row->id,
                'name' => $row->name,
                'ruc' => $row->ruc,
                'value' => (int) $row->id,
                'label' => $row->name,
            ];
        }, $data->items());

        return response()->json([
            'data' => $items,
            'success' => true,
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }
    public function storeHorarios(Request $request)
    {
        try {
            Log::info('storeHorarios: ' . json_encode($request->all()));

            $request->validate([
                'dayData' => 'required|array',
                'dayData.*.day' => 'required|date',
                'dayData.*.startTime' => 'required|date_format:H:i',
                'dayData.*.endTime' => 'required|date_format:H:i',
                'dayData.*.maxBookings' => 'required|integer|min:1',
                'idContenedor' => 'required|integer|exists:carga_consolidada_contenedor,id'
            ]);

            $dayData = $request->input('dayData');
            $idContenedor = $request->input('idContenedor');

            DB::beginTransaction();

            $createdDates = [];
            $createdRanges = [];

            foreach ($dayData as $day) {
                $dayDate = $day['day'];
                $startTime = $day['startTime'];
                $endTime = $day['endTime'];
                $maxBookings = $day['maxBookings'];

                // Parsear fecha y extraer día, mes, año
                $date = \Carbon\Carbon::parse($dayDate);
                $dayNumber = $date->day;
                $monthNumber = $date->month;
                $yearNumber = $date->year;

                // Crear o encontrar la fecha en consolidado_delivery_date
                $deliveryDate = DB::table('consolidado_delivery_date')
                    ->where('id_contenedor', $idContenedor)
                    ->where('day', $dayNumber)
                    ->where('month', $monthNumber)
                    ->where('year', $yearNumber)
                    ->first();

                if (!$deliveryDate) {
                    $dateId = DB::table('consolidado_delivery_date')->insertGetId([
                        'id_contenedor' => $idContenedor,
                        'day' => $dayNumber,
                        'month' => $monthNumber,
                        'year' => $yearNumber,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $createdDates[] = $dateId;
                } else {
                    $dateId = $deliveryDate->id;
                }

                // Crear rangos de 30 minutos
                $start = \Carbon\Carbon::createFromFormat('H:i', $startTime);
                $end = \Carbon\Carbon::createFromFormat('H:i', $endTime);

                while ($start->lt($end)) {
                    $rangeEnd = $start->copy()->addMinutes(30);

                    // Asegurar que no exceda el tiempo final
                    if ($rangeEnd->gt($end)) {
                        $rangeEnd = $end;
                    }

                    // Verificar si ya existe este rango
                    $existingRange = DB::table('consolidado_delivery_range_date')
                        ->where('id_date', $dateId)
                        ->where('start_time', $start->format('H:i:s'))
                        ->where('end_time', $rangeEnd->format('H:i:s'))
                        ->first();

                    if (!$existingRange) {
                        $rangeId = DB::table('consolidado_delivery_range_date')->insertGetId([
                            'id_date' => $dateId,
                            'start_time' => $start->format('H:i:s'),
                            'end_time' => $rangeEnd->format('H:i:s'),
                            'delivery_count' => $maxBookings,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $createdRanges[] = $rangeId;
                    }

                    $start->addMinutes(30);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Horarios creados exitosamente',
                'data' => [
                    'created_dates' => count($createdDates),
                    'created_ranges' => count($createdRanges),
                    'total_days' => count($dayData)
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en storeHorarios: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al crear horarios: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }
    public function editHorarios(Request $request)
    {
        try {
            Log::info('editHorarios: ' . json_encode($request->all()));

            $request->validate([
                'days' => 'required|array',
                'days.dayData' => 'required|array',
                'days.dayData.*.day' => 'required|date',
                'days.dayData.*.startTime' => 'required|date_format:H:i',
                'days.dayData.*.endTime' => 'required|date_format:H:i',
                'days.dayData.*.maxBookings' => 'required|integer|min:1',
                'days.idContenedor' => 'required|integer|exists:carga_consolidada_contenedor,id',
                'ranges' => 'required|array',
                'ranges.*.id' => 'required|integer',
                'ranges.*.time' => 'required|date_format:H:i:s',
                'ranges.*.endTime' => 'required|date_format:H:i:s',
                'ranges.*.isAvailable' => 'required|boolean',
                'ranges.*.maxCapacity' => 'required|integer|min:1',
                'ranges.*.currentBookings' => 'required|integer|min:0'
            ]);

            $days = $request->input('days');
            $dayData = $days['dayData'];
            $idContenedor = $days['idContenedor'];
            $rangesExistentes = $request->input('ranges');

            DB::beginTransaction();

            $processedDays = [];
            $deletedRanges = [];
            $addedRanges = [];
            $skippedRanges = [];
            $deletedCount = 0;
            $addedCount = 0;
            $skippedCount = 0;

            foreach ($dayData as $day) {
                $dayDate = $day['day'];
                $startTime = $day['startTime'];
                $endTime = $day['endTime'];
                $maxBookings = $day['maxBookings'];

                // Parsear fecha y extraer día, mes, año
                $date = \Carbon\Carbon::parse($dayDate);
                $dayNumber = $date->day;
                $monthNumber = $date->month;
                $yearNumber = $date->year;

                // Crear o encontrar la fecha en consolidado_delivery_date
                $deliveryDate = DB::table('consolidado_delivery_date')
                    ->where('id_contenedor', $idContenedor)
                    ->where('day', $dayNumber)
                    ->where('month', $monthNumber)
                    ->where('year', $yearNumber)
                    ->first();

                if (!$deliveryDate) {
                    $dateId = DB::table('consolidado_delivery_date')->insertGetId([
                        'id_contenedor' => $idContenedor,
                        'day' => $dayNumber,
                        'month' => $monthNumber,
                        'year' => $yearNumber,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                } else {
                    $dateId = $deliveryDate->id;
                }

                // 1. Generar arreglo toInsert con rangos de 30 min según startTime y endTime
                $start = \Carbon\Carbon::createFromFormat('H:i', $startTime);
                $end = \Carbon\Carbon::createFromFormat('H:i', $endTime);
                $toInsert = [];

                while ($start->lt($end)) {
                    $rangeEnd = $start->copy()->addMinutes(30);

                    if ($rangeEnd->gt($end)) {
                        $rangeEnd = $end;
                    }

                    $toInsert[] = [
                        'start_time' => $start->format('H:i:s'),
                        'end_time' => $rangeEnd->format('H:i:s')
                    ];

                    $start->addMinutes(30);
                }

                // 2. Obtener todos los rangos existentes de BD para esta fecha
                $allDbRanges = DB::table('consolidado_delivery_range_date')
                    ->where('id_date', $dateId)
                    ->get()
                    ->keyBy('id');

                Log::info("Día: $dayDate, ID: $dateId, Rangos existentes en BD: " . $allDbRanges->count());
                Log::info("Rangos a insertar: " . count($toInsert));

                // 3. Por cada rango existente en BD, validar:
                foreach ($allDbRanges as $dbRange) {
                    Log::info("Procesando rango BD: {$dbRange->start_time} - {$dbRange->end_time} (ID: {$dbRange->id})");

                    $existsInToInsert = collect($toInsert)->contains(function ($range) use ($dbRange) {
                        return $range['start_time'] === $dbRange->start_time &&
                            $range['end_time'] === $dbRange->end_time;
                    });

                    if (!$existsInToInsert) {
                        // Existe en ranges existentes pero NO en toInsert -> BORRAR (si no hay form asociado)
                        $hasForms = DB::table('consolidado_user_range_delivery')
                            ->where('id_range_date', $dbRange->id)
                            ->exists();

                        Log::info("Rango {$dbRange->start_time} - {$dbRange->end_time} NO está en toInsert. Tiene formularios: " . ($hasForms ? 'SÍ' : 'NO'));

                        if (!$hasForms) {
                            DB::table('consolidado_delivery_range_date')
                                ->where('id', $dbRange->id)
                                ->delete();
                            $deletedRanges[] = $dbRange->id;
                            $deletedCount++;
                            Log::info("Rango {$dbRange->start_time} - {$dbRange->end_time} ELIMINADO");
                        } else {
                            Log::info("Rango {$dbRange->start_time} - {$dbRange->end_time} NO eliminado por tener formularios asociados");
                        }
                    } else {
                        // Existe en ranges existentes Y en toInsert -> SKIPEAR
                        $skippedRanges[] = $dbRange->id;
                        $skippedCount++;
                        Log::info("Rango {$dbRange->start_time} - {$dbRange->end_time} OMITIDO (existe en toInsert)");
                    }
                }

                // 4. Por cada rango en toInsert, validar:
                foreach ($toInsert as $rangeToInsert) {
                    $existsInDb = $allDbRanges->contains(function ($dbRange) use ($rangeToInsert) {
                        return $dbRange->start_time === $rangeToInsert['start_time'] &&
                            $dbRange->end_time === $rangeToInsert['end_time'];
                    });

                    if (!$existsInDb) {
                        // Validar una vez más antes de insertar si existe un rango con el mismo start_time y end_time para el mismo día
                        $duplicateExists = DB::table('consolidado_delivery_range_date')
                            ->where('id_date', $dateId)
                            ->where('start_time', $rangeToInsert['start_time'])
                            ->where('end_time', $rangeToInsert['end_time'])
                            ->exists();

                        if (!$duplicateExists) {
                            // NO existe en ranges existentes pero SÍ en toInsert -> INSERTAR
                            $rangeId = DB::table('consolidado_delivery_range_date')->insertGetId([
                                'id_date' => $dateId,
                                'start_time' => $rangeToInsert['start_time'],
                                'end_time' => $rangeToInsert['end_time'],
                                'delivery_count' => $maxBookings,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                            $addedRanges[] = $rangeId;
                            $addedCount++;
                        } else {
                            // Existe duplicado -> SKIPEAR
                            $skippedCount++;
                        }
                    }
                }

                $processedDays[] = [
                    'day' => $dayDate,
                    'date_id' => $dateId,
                    'to_insert_count' => count($toInsert)
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Horarios editados exitosamente',
                'data' => [
                    'processed_days' => count($processedDays),
                    'deleted_count' => $deletedCount,
                    'added_count' => $addedCount,
                    'skipped_count' => $skippedCount,
                    'deleted_range_ids' => $deletedRanges,
                    'added_range_ids' => $addedRanges,
                    'skipped_range_ids' => $skippedRanges
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en editHorarios: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al editar horarios: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }
    public function deleteHorarios(Request $request)
    {
        try {
            Log::info('deleteHorarios: ' . json_encode($request->all()));

            $request->validate([
                'idContenedor' => 'required|integer|exists:carga_consolidada_contenedor,id',
                'timeSlots' => 'required|array',
                'timeSlots.*.id' => 'required|integer',
                'timeSlots.*.time' => 'required|date_format:H:i:s',
                'timeSlots.*.endTime' => 'required|date_format:H:i:s',
                'timeSlots.*.isAvailable' => 'required|boolean',
                'timeSlots.*.maxCapacity' => 'required|integer|min:1',
                'timeSlots.*.currentBookings' => 'required|integer|min:0'
            ]);

            $idContenedor = $request->input('idContenedor');
            $timeSlots = $request->input('timeSlots');

            DB::beginTransaction();

            $deletedRanges = [];
            $skippedRanges = [];
            $deletedCount = 0;
            $skippedCount = 0;

            foreach ($timeSlots as $timeSlot) {
                $rangeId = $timeSlot['id'];
                $startTime = $timeSlot['time'];
                $endTime = $timeSlot['endTime'];

                // Verificar si el rango existe en la BD
                $dbRange = DB::table('consolidado_delivery_range_date')
                    ->where('id', $rangeId)
                    ->first();

                if (!$dbRange) {
                    Log::info("Rango ID $rangeId no encontrado en BD");
                    continue;
                }

                // Verificar si el rango pertenece al contenedor correcto
                $deliveryDate = DB::table('consolidado_delivery_date')
                    ->where('id', $dbRange->id_date)
                    ->where('id_contenedor', $idContenedor)
                    ->first();

                if (!$deliveryDate) {
                    Log::info("Rango ID $rangeId no pertenece al contenedor $idContenedor");
                    continue;
                }

                // Verificar si tiene clientes/formularios asociados
                $hasForms = DB::table('consolidado_user_range_delivery')
                    ->where('id_range_date', $rangeId)
                    ->exists();

                if (!$hasForms) {
                    // No tiene formularios asociados -> BORRAR
                    DB::table('consolidado_delivery_range_date')
                        ->where('id', $rangeId)
                        ->delete();

                    $deletedRanges[] = $rangeId;
                    $deletedCount++;
                    Log::info("Rango $startTime - $endTime (ID: $rangeId) ELIMINADO");
                } else {
                    // Tiene formularios asociados -> NO BORRAR
                    $skippedRanges[] = $rangeId;
                    $skippedCount++;
                    Log::info("Rango $startTime - $endTime (ID: $rangeId) NO eliminado por tener formularios asociados");
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Horarios eliminados exitosamente',
                'data' => [
                    'deleted_count' => $deletedCount,
                    'skipped_count' => $skippedCount,
                    'deleted_range_ids' => $deletedRanges,
                    'skipped_range_ids' => $skippedRanges
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en deleteHorarios: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar horarios: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }
    public function seleccionHorarios(Request $request)
    {
        try {
            $arrayTimes = $request->slots;
            //array with objects with id and selected
            $arrayTimes = array_map(function ($time) {
                return (object) [
                    'id' => $time['id'],
                    'selected' => $time['selected']
                ];
            }, $arrayTimes);

            DB::beginTransaction();
            foreach ($arrayTimes as $time) {
                if ($time->selected) {
                    DB::table('consolidado_delivery_range_date')
                        ->where('id', $time->id)
                        ->update(['is_hidden' => 1]);
                } else {
                    DB::table('consolidado_delivery_range_date')
                        ->where('id', $time->id)
                        ->update(['is_hidden' => 0]);
                }
            }
            DB::commit();
            return response()->json(['message' => 'Horarios seleccionados correctamente', 'success' => true], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al seleccionar horarios: ' . $e->getMessage(), 'success' => false], 500);
        }
    }
}
