<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Models\Calendar\Calendar;
use App\Services\Calendar\CalendarEventService;
use App\Services\Calendar\CalendarPermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CalendarController extends Controller
{
    protected CalendarEventService $eventService;
    protected CalendarPermissionService $permissionService;

    public function __construct(CalendarEventService $eventService, CalendarPermissionService $permissionService)
    {
        $this->eventService = $eventService;
        $this->permissionService = $permissionService;
    }

    /**
     * GET /api/calendar/events
     * Query: start_date, end_date, responsable_id, contenedor_id (uno) o contenedor_ids[] (varios), status, priority.
     */
    public function getEvents(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->getIdUsuario();
            $onlyMyCharges = !$this->permissionService->isJefeImportaciones($user);

            $contenedorIds = $request->input('contenedor_ids');
            if (is_array($contenedorIds)) {
                $contenedorIds = array_filter(array_map('intval', $contenedorIds));
            } elseif (is_string($contenedorIds) && $contenedorIds !== '') {
                $contenedorIds = array_filter(array_map('intval', explode(',', $contenedorIds)));
            } elseif (is_numeric($contenedorIds) || (is_string($contenedorIds) && is_numeric(trim($contenedorIds)))) {
                $contenedorIds = [(int) $contenedorIds];
            } elseif ($request->has('contenedor_id') && $request->input('contenedor_id') !== null && $request->input('contenedor_id') !== '') {
                $contenedorIds = [(int) $request->input('contenedor_id')];
            } else {
                $contenedorIds = null;
            }
            if ($contenedorIds !== null && empty($contenedorIds)) {
                $contenedorIds = null;
            }

            // Responsable(s): aceptar responsable_ids[] (varios) o responsable_id (uno). "Todos" = no enviar.
            $responsableIds = null;
            $rawResponsableIds = $request->input('responsable_ids');
            if (is_array($rawResponsableIds)) {
                $responsableIds = array_filter(array_map('intval', $rawResponsableIds));
            } elseif (is_string($rawResponsableIds) && $rawResponsableIds !== '') {
                $responsableIds = array_filter(array_map('intval', explode(',', $rawResponsableIds)));
            }
            if ($responsableIds !== null && empty($responsableIds)) {
                $responsableIds = null;
            }
            if ($responsableIds === null) {
                $rawOne = $request->input('responsable_id');
                if ($rawOne !== null && $rawOne !== '' && is_numeric($rawOne)) {
                    $oneId = (int) $rawOne;
                    if (!$onlyMyCharges || $oneId === $userId) {
                        $responsableIds = [$oneId];
                    }
                }
            } elseif ($onlyMyCharges) {
                $responsableIds = array_intersect($responsableIds, [$userId]);
                if (empty($responsableIds)) {
                    $responsableIds = null;
                }
            }

            $events = $this->eventService->getEventsForUser(
                $userId,
                $request->input('start_date'),
                $request->input('end_date'),
                $responsableIds,
                $contenedorIds,
                $request->input('status'),
                $request->input('priority') !== null ? (int) $request->input('priority') : null,
                $onlyMyCharges
            );
            return response()->json([
                'success' => true,
                'data' => $events->values()->all(),
                'message' => 'Actividades obtenidas correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('CalendarController@getEvents: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener eventos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/calendar/events/{id}
     */
    public function getEvent(int $id): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->getIdUsuario();
            $event = $this->eventService->getEventById($id, $userId, $this->permissionService->isJefeImportaciones($user));
            if (!$event) {
                return response()->json(['success' => false, 'message' => 'Evento no encontrado'], 404);
            }
            return response()->json(['success' => true, 'data' => $event]);
        } catch (\Exception $e) {
            Log::error('CalendarController@getEvent: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener evento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/calendar/events
     * Crear evento. Acepta: name/title, start_date, end_date, activity_id?, responsible_user_ids?, contenedor_id?, notes?
     * Si no se envía calendar_id se usa el calendario del usuario.
     */
    public function createEvent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'calendar_id' => 'nullable|integer|exists:calendars,id',
            'name' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'activity_id' => 'nullable|integer|exists:calendar_activities,id',
            'responsible_user_ids' => 'nullable|array',
            'responsible_user_ids.*' => 'integer|exists:usuario,ID_Usuario',
            'contenedor_id' => 'nullable|integer|exists:carga_consolidada_contenedor,id',
            'notes' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$this->permissionService->canManageActivities($user)) {
                return response()->json(['success' => false, 'message' => 'No tienes permiso para crear actividades'], 403);
            }
            $userId = $user->getIdUsuario();
            $calendarId = $request->input('calendar_id');
            if (!$calendarId) {
                $calendar = Calendar::firstOrCreate(
                    ['user_id' => $userId],
                    ['user_id' => $userId]
                );
                $calendarId = $calendar->id;
            }
            $data = $request->only([
                'activity_id', 'name', 'title', 'start_date', 'end_date',
                'responsible_user_ids', 'responsable_ids', 'contenedor_id', 'notes',
            ]);
            $event = $this->eventService->createActivityEvent((int) $calendarId, $data, $userId);
            $formatted = $this->eventService->formatEventForResponse($event);
            return response()->json(['success' => true, 'data' => $formatted, 'message' => 'Actividad creada correctamente'], 201);
        } catch (\Exception $e) {
            Log::error('CalendarController@createEvent: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear evento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/calendar/events/{id}
     */
    public function updateEvent(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'activity_id' => 'nullable|integer|exists:calendar_activities,id',
            'responsible_user_ids' => 'nullable|array',
            'responsible_user_ids.*' => 'integer|exists:usuario,ID_Usuario',
            'contenedor_id' => 'nullable|integer|exists:carga_consolidada_contenedor,id',
            'notes' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$this->permissionService->canManageActivities($user)) {
                return response()->json(['success' => false, 'message' => 'No tienes permiso para editar actividades'], 403);
            }
            $userId = $user->getIdUsuario();
            $data = $request->only([
                'name', 'title', 'start_date', 'end_date', 'activity_id',
                'responsible_user_ids', 'responsable_ids', 'contenedor_id', 'notes', 'priority',
            ]);
            $event = $this->eventService->updateEvent($id, $userId, $data, $this->permissionService->canManageActivities($user));
            if (!$event) {
                return response()->json(['success' => false, 'message' => 'Evento no encontrado'], 404);
            }
            $formatted = $this->eventService->formatEventForResponse($event);
            return response()->json(['success' => true, 'data' => $formatted, 'message' => 'Actividad actualizada correctamente']);
        } catch (\Exception $e) {
            Log::error('CalendarController@updateEvent: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar evento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/calendar/events/{id}
     */
    public function deleteEvent(int $id): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$this->permissionService->canManageActivities($user)) {
                return response()->json(['success' => false, 'message' => 'No tienes permiso para eliminar actividades'], 403);
            }
            $userId = $user->getIdUsuario();
            $deleted = $this->eventService->deleteEvent($id, $userId, $this->permissionService->canManageActivities($user));
            if (!$deleted) {
                return response()->json(['success' => false, 'message' => 'Evento no encontrado'], 404);
            }
            return response()->json(['success' => true, 'message' => 'Actividad eliminada correctamente']);
        } catch (\Exception $e) {
            Log::error('CalendarController@deleteEvent: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar evento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/calendar/events/{id}/move
     * Cambiar solo fechas del evento.
     */
    public function moveEvent(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        return $this->updateEvent($request, $id);
    }

    /**
     * PUT /api/calendar/events/{id}/status
     * Estado por actividad: cualquier participante puede cambiarlo; se aplica a todos los responsables.
     */
    public function updateEventStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:PENDIENTE,PROGRESO,COMPLETADO',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->getIdUsuario();
            $event = $this->eventService->updateEventStatus($id, $userId, $request->input('status'));
            if (!$event) {
                return response()->json(['success' => false, 'message' => 'Actividad no encontrada o no eres participante'], 404);
            }
            $formatted = $this->eventService->formatEventForResponse($event);
            return response()->json(['success' => true, 'data' => $formatted, 'message' => 'Estado actualizado correctamente']);
        } catch (\Exception $e) {
            Log::error('CalendarController@updateEventStatus: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
