<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Models\Calendar\Evento;
use App\Models\Calendar\TaskDay;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


class CalendarController extends Controller
{
    /**
     * @OA\Get(
     *     path="/calendar/events",
     *     tags={"Calendario"},
     *     summary="Obtener eventos del calendario",
     *     description="Obtiene eventos y tareas del calendario para el usuario autenticado",
     *     operationId="getCalendarEvents",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Fecha de inicio del rango (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Fecha de fin del rango (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="role_id",
     *         in="query",
     *         description="Filtrar por ID de rol",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Eventos obtenidos exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="start_date", type="string", format="date"),
     *                 @OA\Property(property="end_date", type="string", format="date"),
     *                 @OA\Property(property="type", type="string", enum={"evento", "tarea"})
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     *
     * Obtener eventos con filtros
     */
    public function getEvents(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->getIdUsuario();
            $userRoleName = $user->getNombreGrupo();

            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Obtener eventos normales
            $query = Evento::visibleForUser($userId, $userRoleName)
                ->where('type', 'evento')
                ->whereNull('parent_task_id');

            if ($startDate && $endDate) {
                $query->inDateRange($startDate, $endDate);
            }

            if ($request->has('role_id')) {
                $query->where('role_id', $request->role_id);
            }

            $events = $query->orderBy('start_date')
                ->orderBy('start_time')
                ->get();

            // Obtener días de tareas visibles
            $taskDays = [];
            if ($startDate && $endDate) {
                $taskQuery = TaskDay::whereHas('task', function ($q) use ($userId, $userRoleName) {
                    $q->visibleForUser($userId, $userRoleName);
                })
                ->whereBetween('day_date', [$startDate, $endDate]);

                $taskDaysData = $taskQuery->with('task')->get();

                // Convertir días de tareas a formato de eventos para el frontend
                foreach ($taskDaysData as $taskDay) {
                    $task = $taskDay->task;
                    $taskDays[] = [
                        'id' => $taskDay->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'start_date' => $taskDay->day_date->format('Y-m-d'),
                        'end_date' => $taskDay->day_date->format('Y-m-d'),
                        'start_time' => $taskDay->start_time ? $taskDay->start_time->format('H:i') : null,
                        'end_time' => $taskDay->end_time ? $taskDay->end_time->format('H:i') : null,
                        'is_all_day' => $taskDay->is_all_day,
                        'is_for_me' => $task->is_for_me,
                        'role_id' => $task->role_id,
                        'role_name' => $task->role_name,
                        'is_public' => $task->is_public,
                        'created_by' => $task->created_by,
                        'created_by_name' => $task->created_by_name,
                        'color' => $taskDay->color ?? $task->color,
                        'type' => 'tarea',
                        'parent_task_id' => $task->id,
                        'task_day_id' => $taskDay->id,
                        'created_at' => $taskDay->created_at,
                        'updated_at' => $taskDay->updated_at
                    ];
                }
            }

            // Convertir eventos a arrays
            $eventsArray = $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'start_date' => $event->start_date->format('Y-m-d'),
                    'end_date' => $event->end_date->format('Y-m-d'),
                    'start_time' => $event->start_time ? $event->start_time->format('H:i') : null,
                    'end_time' => $event->end_time ? $event->end_time->format('H:i') : null,
                    'is_all_day' => $event->is_all_day,
                    'is_for_me' => $event->is_for_me,
                    'role_id' => $event->role_id,
                    'role_name' => $event->role_name,
                    'is_public' => $event->is_public,
                    'created_by' => $event->created_by,
                    'created_by_name' => $event->created_by_name,
                    'color' => $event->color,
                    'type' => $event->type ?? 'evento',
                    'parent_task_id' => $event->parent_task_id,
                    'created_at' => $event->created_at,
                    'updated_at' => $event->updated_at
                ];
            })->toArray();

            // Combinar eventos y días de tareas
            $allEvents = array_merge($eventsArray, $taskDays);

            return response()->json([
                'success' => true,
                'data' => $allEvents
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener eventos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener eventos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un evento por ID
     */
    public function getEvent($id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->getIdUsuario();
            $userRoleName = $user->getNombreGrupo();

            $event = Evento::visibleForUser($userId, $userRoleName)
                ->where('id', $id)
                ->first();

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Evento no encontrado o no tienes permiso para verlo'
                ], 404);
            }

            return response()->json($event);
        } catch (\Exception $e) {
            Log::error('Error al obtener evento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener evento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/calendar/events",
     *     tags={"Calendario"},
     *     summary="Crear evento",
     *     description="Crea un nuevo evento o tarea en el calendario",
     *     operationId="createCalendarEvent",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "start_date", "end_date"},
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date"),
     *             @OA\Property(property="start_time", type="string", format="time"),
     *             @OA\Property(property="end_time", type="string", format="time"),
     *             @OA\Property(property="is_all_day", type="boolean"),
     *             @OA\Property(property="is_for_me", type="boolean"),
     *             @OA\Property(property="is_public", type="boolean"),
     *             @OA\Property(property="type", type="string", enum={"evento", "tarea"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Evento creado exitosamente"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     *
     * Crear un nuevo evento
     */
    public function createEvent(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->getIdUsuario();
            $userRoleName = $user->getNombreGrupo();

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
                'is_all_day' => 'boolean',
                'is_for_me' => 'boolean',
                'is_public' => 'boolean',
                'type' => 'nullable|in:evento,tarea'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->all();
            $type = $data['type'] ?? 'evento';
            
            // Si es para mi rol, obtener el ID del grupo del usuario
            if (isset($data['is_for_my_role']) && $data['is_for_my_role']) {
                $grupo = $user->grupo;
                if ($grupo) {
                    $data['role_id'] = $grupo->ID_Grupo;
                    $data['role_name'] = $grupo->No_Grupo;
                }
                unset($data['is_for_my_role']);
            } else if (!isset($data['is_for_my_role']) || !$data['is_for_my_role']) {
                $data['role_id'] = null;
                $data['role_name'] = null;
            }

            // Si es solo para mí, asegurar que is_for_me sea true
            if (isset($data['is_for_me']) && $data['is_for_me']) {
                $data['role_id'] = null;
                $data['role_name'] = null;
            }

            // Si es público, no puede ser para mí o para mi rol
            if (isset($data['is_public']) && $data['is_public']) {
                $data['is_for_me'] = false;
                $data['role_id'] = null;
                $data['role_name'] = null;
            }

            $data['created_by'] = $userId;
            $data['created_by_name'] = $user->No_Nombres_Apellidos ?? $user->No_Usuario;
            $data['type'] = $type;

            // Generar color aleatorio si no se proporciona
            if (!isset($data['color'])) {
                $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
                $data['color'] = $colors[array_rand($colors)];
            }

            DB::beginTransaction();
            try {
                if ($type === 'tarea') {
                    // Crear la tarea principal
                    $task = Evento::create($data);
                    
                    // Crear días de la tarea desde start_date hasta end_date
                    $startDate = new \DateTime($data['start_date']);
                    $endDate = new \DateTime($data['end_date']);
                    $currentDate = clone $startDate;
                    
                    while ($currentDate <= $endDate) {
                        TaskDay::create([
                            'task_id' => $task->id,
                            'day_date' => $currentDate->format('Y-m-d'),
                            'start_time' => $data['start_time'] ?? null,
                            'end_time' => $data['end_time'] ?? null,
                            'is_all_day' => $data['is_all_day'] ?? true,
                            'color' => $data['color']
                        ]);
                        
                        $currentDate->modify('+1 day');
                    }
                    
                    DB::commit();
                    $task->load('taskDays');
                    return response()->json($task, 201);
                } else {
                    // Crear evento normal
                    $event = Evento::create($data);
                    DB::commit();
                    return response()->json($event, 201);
                }
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error al crear evento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear evento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/calendar/events/{id}",
     *     tags={"Calendario"},
     *     summary="Actualizar evento",
     *     description="Actualiza un evento o día de tarea existente",
     *     operationId="updateCalendarEvent",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="title", type="string"),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="start_date", type="string", format="date"),
     *         @OA\Property(property="end_date", type="string", format="date")
     *     )),
     *     @OA\Response(response=200, description="Evento actualizado exitosamente"),
     *     @OA\Response(response=404, description="Evento no encontrado"),
     *     @OA\Response(response=403, description="Sin permiso para editar")
     * )
     *
     * Actualizar un evento o día de tarea
     */
    public function updateEvent(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->getIdUsuario();
            $userRoleName = $user->getNombreGrupo();

            // Verificar si es un día de tarea (task_day_id viene en el request)
            if ($request->has('task_day_id') && $request->task_day_id) {
                return $this->updateTaskDayInternal($request, $request->task_day_id, $userId);
            }

            $event = Evento::where('id', $id)->first();

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Evento no encontrado'
                ], 404);
            }

            // Solo el creador puede actualizar el evento
            if ($event->created_by != $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para actualizar este evento'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'sometimes|required|date',
                'end_date' => 'sometimes|required|date|after_or_equal:start_date',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
                'is_all_day' => 'boolean',
                'is_for_me' => 'boolean',
                'is_public' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->all();

            // Si es para mi rol, obtener el ID del grupo del usuario
            if (isset($data['is_for_my_role']) && $data['is_for_my_role']) {
                $grupo = $user->grupo;
                if ($grupo) {
                    $data['role_id'] = $grupo->ID_Grupo;
                    $data['role_name'] = $grupo->No_Grupo;
                }
                unset($data['is_for_my_role']);
            } else if (isset($data['is_for_my_role'])) {
                $data['role_id'] = null;
                $data['role_name'] = null;
                unset($data['is_for_my_role']);
            }

            // Si es solo para mí, asegurar que is_for_me sea true
            if (isset($data['is_for_me']) && $data['is_for_me']) {
                $data['role_id'] = null;
                $data['role_name'] = null;
            }

            // Si es público, no puede ser para mí o para mi rol
            if (isset($data['is_public']) && $data['is_public']) {
                $data['is_for_me'] = false;
                $data['role_id'] = null;
                $data['role_name'] = null;
            }

            DB::beginTransaction();
            try {
                // Si es una tarea y se actualiza el rango de fechas, actualizar los días
                if ($event->type === 'tarea' && isset($data['start_date']) && isset($data['end_date'])) {
                    $event->update($data);
                    
                    // Eliminar días fuera del nuevo rango
                    TaskDay::where('task_id', $event->id)
                        ->where(function($q) use ($data) {
                            $q->where('day_date', '<', $data['start_date'])
                              ->orWhere('day_date', '>', $data['end_date']);
                        })
                        ->delete();
                    
                    // Agregar días nuevos si el rango se expandió
                    $startDate = new \DateTime($data['start_date']);
                    $endDate = new \DateTime($data['end_date']);
                    $currentDate = clone $startDate;
                    
                    while ($currentDate <= $endDate) {
                        $dateStr = $currentDate->format('Y-m-d');
                        $existingDay = TaskDay::where('task_id', $event->id)
                            ->where('day_date', $dateStr)
                            ->first();
                        
                        if (!$existingDay) {
                            TaskDay::create([
                                'task_id' => $event->id,
                                'day_date' => $dateStr,
                                'start_time' => $data['start_time'] ?? $event->start_time,
                                'end_time' => $data['end_time'] ?? $event->end_time,
                                'is_all_day' => $data['is_all_day'] ?? $event->is_all_day,
                                'color' => $data['color'] ?? $event->color
                            ]);
                        }
                        
                        $currentDate->modify('+1 day');
                    }
                } else {
                    $event->update($data);
                }
                
                DB::commit();
                $event->load('taskDays');
                return response()->json($event);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error al actualizar evento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar evento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un día específico de una tarea
     */
    public function updateTaskDay(Request $request, $taskDayId)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $userId = $user->getIdUsuario();
        return $this->updateTaskDayInternal($request, $taskDayId, $userId);
    }

    /**
     * Método interno para actualizar un día específico de una tarea
     */
    private function updateTaskDayInternal(Request $request, $taskDayId, $userId)
    {
        try {
            $taskDay = TaskDay::find($taskDayId);
            
            if (!$taskDay) {
                return response()->json([
                    'success' => false,
                    'message' => 'Día de tarea no encontrado'
                ], 404);
            }

            $task = $taskDay->task;
            
            // Solo el creador puede actualizar
            if ($task->created_by != $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para actualizar este día'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
                'is_all_day' => 'boolean',
                'day_date' => 'sometimes|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->all();
            $updateData = [];
            
            if (isset($data['start_time'])) {
                $updateData['start_time'] = $data['start_time'];
            }
            if (isset($data['end_time'])) {
                $updateData['end_time'] = $data['end_time'];
            }
            if (isset($data['is_all_day'])) {
                $updateData['is_all_day'] = $data['is_all_day'];
            }
            if (isset($data['day_date'])) {
                $updateData['day_date'] = $data['day_date'];
            }
            if (isset($data['color'])) {
                $updateData['color'] = $data['color'];
            }

            $taskDay->update($updateData);
            $taskDay->load('task');

            // Retornar en formato de evento para el frontend
            return response()->json([
                'id' => $taskDay->id,
                'title' => $task->title,
                'description' => $task->description,
                'start_date' => $taskDay->day_date->format('Y-m-d'),
                'end_date' => $taskDay->day_date->format('Y-m-d'),
                'start_time' => $taskDay->start_time ? $taskDay->start_time->format('H:i') : null,
                'end_time' => $taskDay->end_time ? $taskDay->end_time->format('H:i') : null,
                'is_all_day' => $taskDay->is_all_day,
                'is_for_me' => $task->is_for_me,
                'role_id' => $task->role_id,
                'role_name' => $task->role_name,
                'is_public' => $task->is_public,
                'created_by' => $task->created_by,
                'created_by_name' => $task->created_by_name,
                'color' => $taskDay->color ?? $task->color,
                'type' => 'tarea',
                'parent_task_id' => $task->id,
                'task_day_id' => $taskDay->id
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar día de tarea: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar día de tarea',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/calendar/events/{id}",
     *     tags={"Calendario"},
     *     summary="Eliminar evento",
     *     description="Elimina un evento o día de tarea",
     *     operationId="deleteCalendarEvent",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="task_day_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Evento eliminado exitosamente"),
     *     @OA\Response(response=404, description="Evento no encontrado"),
     *     @OA\Response(response=403, description="Sin permiso para eliminar")
     * )
     *
     * Eliminar un evento o día de tarea
     */
    public function deleteEvent(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->getIdUsuario();

            // Si viene task_day_id en query params, eliminar solo ese día
            $taskDayId = $request ? $request->input('task_day_id') : null;
            if ($taskDayId) {
                $taskDay = TaskDay::find($taskDayId);
                
                if (!$taskDay) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Día de tarea no encontrado'
                    ], 404);
                }

                $task = $taskDay->task;
                
                if ($task->created_by != $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes permiso para eliminar este día'
                    ], 403);
                }

                $taskDay->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Día de tarea eliminado correctamente'
                ]);
            }

            // Eliminar evento o tarea completa
            $event = Evento::where('id', $id)->first();

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Evento no encontrado'
                ], 404);
            }

            // Solo el creador puede eliminar el evento
            if ($event->created_by != $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar este evento'
                ], 403);
            }

            // Si es una tarea, eliminar también todos sus días (cascade)
            $event->delete();

            return response()->json([
                'success' => true,
                'message' => 'Evento eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar evento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar evento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mover un evento (cambiar fechas/horas)
     */
    public function moveEvent(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->getIdUsuario();

            $event = Evento::where('id', $id)->first();

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Evento no encontrado'
                ], 404);
            }

            // Solo el creador puede mover el evento
            if ($event->created_by != $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para mover este evento'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $event->update([
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time
            ]);

            return response()->json($event);
        } catch (\Exception $e) {
            Log::error('Error al mover evento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al mover evento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un día específico de una tarea
     */
    public function deleteTaskDay($taskDayId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->getIdUsuario();

            $taskDay = TaskDay::find($taskDayId);
            
            if (!$taskDay) {
                return response()->json([
                    'success' => false,
                    'message' => 'Día de tarea no encontrado'
                ], 404);
            }

            $task = $taskDay->task;
            
            if ($task->created_by != $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar este día'
                ], 403);
            }

            $taskDay->delete();

            return response()->json([
                'success' => true,
                'message' => 'Día de tarea eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar día de tarea: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar día de tarea',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

