<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class EntregaController extends Controller
{
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
                'd.id as date_id', 'd.day', 'd.month', 'd.year',
                'r.id as range_id', 'r.start_time', 'r.end_time', 'r.delivery_count',
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
    public function getClientesEntrega(Request $request, $idContenedor)
    {
        // Lo obtiene de la tabla de clientes asociados al contenedor
        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->join('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
            ->select('C.*','CC.*')
            ->where('CC.id_contenedor', $idContenedor)
            ->whereNotNull('CC.estado_cliente')
            ->whereNull('CC.id_cliente_importacion')
            ->where('CC.estado_cotizador', 'CONFIRMADO');
        // Aplicar filtro de estado si se proporciona
        $page = $request->input('currentPage', 1);
        $perPage = $request->input('itemsPerPage', 100);
        $clientes = $query->paginate($perPage, ['*'], 'currentPage', $page);
        // Aplicar filtros adicionales si se proporcionan
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('CC.nombre', 'LIKE', "%{$search}%")
                    ->orWhere('CC.documento', 'LIKE', "%{$search}%")
                    ->orWhere('CC.correo', 'LIKE', "%{$search}%");
            });
        }
        // Ordenamiento
        $sortField = $request->input('sort_by', 'CC.id');
        $sortOrder = $request->input('sort_order', 'asc');

        // Paginación
        $data = $query->paginate($perPage, ['*'], 'page', $page);

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
        // Lo obtiene de la tabla de clientes asociados al contenedor
        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->join('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
            ->select('C.*','CC.*')
            ->where('CC.id_contenedor', $idContenedor)
            ->whereNotNull('CC.estado_cliente')
            ->whereNull('CC.id_cliente_importacion')
            ->where('CC.estado_cotizador', 'CONFIRMADO');
        // Aplicar filtro de estado si se proporciona
        $page = $request->input('currentPage', 1);
        $perPage = $request->input('itemsPerPage', 100);
        $clientes = $query->paginate($perPage, ['*'], 'currentPage', $page);
        // Aplicar filtros adicionales si se proporcionan
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('CC.nombre', 'LIKE', "%{$search}%")
                    ->orWhere('CC.documento', 'LIKE', "%{$search}%")
                    ->orWhere('CC.correo', 'LIKE', "%{$search}%");
            });
        }
        // Ordenamiento
        $sortField = $request->input('sort_by', 'CC.id');
        $sortOrder = $request->input('sort_order', 'asc');

        // Paginación
        $data = $query->paginate($perPage, ['*'], 'page', $page);

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
    public function getDelivery(Request $request, $idContenedor)
    {
        // Lo obtiene de la tabla de clientes asociados al contenedor
        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->join('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
            ->select('C.*','CC.*')
            ->where('CC.id_contenedor', $idContenedor)
            ->whereNotNull('CC.estado_cliente')
            ->whereNull('CC.id_cliente_importacion')
            ->where('CC.estado_cotizador', 'CONFIRMADO');
            // Aplicar filtro de estado si se proporciona
        $page = $request->input('currentPage', 1);
        $perPage = $request->input('itemsPerPage', 100);
        $clientes = $query->paginate($perPage, ['*'], 'currentPage', $page);
        // Aplicar filtros adicionales si se proporcionan
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('CC.nombre', 'LIKE', "%{$search}%")
                    ->orWhere('CC.documento', 'LIKE', "%{$search}%")
                    ->orWhere('CC.correo', 'LIKE', "%{$search}%");
            });
        }
        // Ordenamiento
        $sortField = $request->input('sort_by', 'CC.id');
        $sortOrder = $request->input('sort_order', 'asc');

        // Paginación
        $data = $query->paginate($perPage, ['*'], 'page', $page);

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
    public function getEntregasDetalle($idContenedor)
    {
        // Lógica para obtener los detalles de una entrega específica
        $entrega = DB::table('contenedor_consolidado_cotizacion')
            ->where('id', $idContenedor)
            ->first();
        if (!$entrega) {
            return response()->json(['message' => 'Entrega no encontrada'], 404);
        }
        return response()->json(['data' => $entrega, 'success' => true]);
    }
}