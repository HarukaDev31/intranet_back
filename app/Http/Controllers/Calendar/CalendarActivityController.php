<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Models\Calendar\Calendar;
use App\Models\Calendar\CalendarActivity;
use App\Models\Calendar\CalendarEvent;
use App\Models\Calendar\CalendarEventCharge;
use App\Models\Calendar\CalendarRoleGroup;
use App\Models\Calendar\CalendarUserColorConfig;
use App\Models\Calendar\CalendarConsolidadoColorConfig;
use App\Models\Calendar\CalendarRoleGroupMember;
use App\Models\Usuario;
use App\Models\CargaConsolidada\Contenedor;
use App\Services\Calendar\CalendarActivityService;
use App\Services\Calendar\CalendarEventService;
use App\Services\Calendar\CalendarPermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Traits\FileTrait;
class CalendarActivityController extends Controller
{
    use FileTrait;
    protected CalendarActivityService $activityService;
    protected CalendarEventService $eventService;
    protected CalendarPermissionService $permissionService;

    public function __construct(
        CalendarActivityService $activityService,
        CalendarEventService $eventService,
        CalendarPermissionService $permissionService
    ) {
        $this->activityService = $activityService;
        $this->eventService = $eventService;
        $this->permissionService = $permissionService;
    }

    /**
     * GET /api/calendar/config - Configuración de calendario para el usuario autenticado.
     * Query opcional: role_group_id (si el usuario pertenece a varios grupos, indica cuál usar).
     */
    public function getCalendarConfig(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $roleGroupId = $request->input('role_group_id');
            $roleGroupId = $roleGroupId !== null && $roleGroupId !== '' ? (int) $roleGroupId : null;
            if ($roleGroupId !== null && !$this->permissionService->userBelongsToRoleGroup($user, $roleGroupId)) {
                return response()->json(['success' => false, 'message' => 'No perteneces a ese grupo de calendario'], 403);
            }
            $config = $this->permissionService->getCalendarConfigForUser($user, $roleGroupId);

            return response()->json([
                'success' => true,
                'data' => $config,
                'message' => 'Configuración de calendario obtenida correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('CalendarActivityController@getCalendarConfig: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener configuración de calendario',
            ], 500);
        }
    }

    /**
     * GET /api/calendar/activity-catalog - Lista actividades del catálogo del grupo del usuario
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $roleGroupId = $this->resolveRoleGroupIdForUser($request, $user);
            if ($roleGroupId === null) {
                return response()->json(['success' => false, 'message' => 'El usuario no tiene grupo de calendario asignado'], 400);
            }
            $activities = $this->activityService->listActivities($roleGroupId);
            $data = $activities->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'orden' => $a->orden ?? 0,
                'color_code' => $a->color_code,
                'allow_saturday' => (bool) $a->allow_saturday,
                'allow_sunday' => (bool) $a->allow_sunday,
                'default_priority' => (int) ($a->default_priority ?? 0),
            ]);
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Catálogo de actividades obtenido',
            ]);
        } catch (\Exception $e) {
            Log::error('CalendarActivityController@index: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al listar actividades'], 500);
        }
    }

    /**
     * POST /api/calendar/activity-catalog - Crear actividad en el catálogo (solo Jefe Importaciones)
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->permissionService->canManageActivities(JWTAuth::parseToken()->authenticate())) {
            return response()->json(['success' => false, 'message' => 'Solo el Jefe de Importaciones puede crear actividades en el catálogo'], 403);
        }
        $user = JWTAuth::parseToken()->authenticate();
        $roleGroupId = $this->resolveRoleGroupIdForUser($request, $user);
        if ($roleGroupId === null) {
            return response()->json(['success' => false, 'message' => 'El usuario no tiene grupo de calendario asignado'], 400);
        }
        $v = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:255',
        ], [
            'name.required' => 'El nombre es requerido',
            'name.min' => 'El nombre debe tener al menos 3 caracteres',
        ]);
        if ($v->fails()) {
            $msg = $v->errors()->first('name') ?: 'Datos inválidos';
            return response()->json(['success' => false, 'message' => $msg], 400);
        }
        $name = $request->input('name');
        if (CalendarActivity::where('name', $name)->where('role_group_id', $roleGroupId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Ya existe una actividad con ese nombre'], 400);
        }
        try {
            $activity = $this->activityService->createActivity($name, $roleGroupId);
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'orden' => $activity->orden ?? 0,
                    'color_code' => $activity->color_code,
                    'allow_saturday' => (bool) $activity->allow_saturday,
                    'allow_sunday' => (bool) $activity->allow_sunday,
                    'default_priority' => (int) ($activity->default_priority ?? 0),
                ],
                'message' => 'Actividad creada en el catálogo',
            ], 201);
        } catch (\Exception $e) {
            Log::error('CalendarActivityController@store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al crear actividad'], 500);
        }
    }

    /**
     * PUT /api/calendar/activity-catalog/{id} - Actualizar actividad del catálogo (solo Jefe Importaciones)
     */
    public function updateCatalog(Request $request, int $id): JsonResponse
    {
        if (!$this->permissionService->canManageActivities(JWTAuth::parseToken()->authenticate())) {
            return response()->json(['success' => false, 'message' => 'No tienes permiso para editar el catálogo'], 403);
        }
        $user = JWTAuth::parseToken()->authenticate();
        $roleGroupId = $this->resolveRoleGroupIdForUser($request, $user);
        if ($roleGroupId === null) {
            return response()->json(['success' => false, 'message' => 'El usuario no tiene grupo de calendario asignado'], 400);
        }
        $v = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:255',
            'color_code' => 'nullable|string|max:20',
            'allow_saturday' => 'nullable|boolean',
            'allow_sunday' => 'nullable|boolean',
            'default_priority' => 'nullable|integer|in:0,1,2',
        ], [
            'name.required' => 'El nombre es requerido',
            'name.min' => 'El nombre debe tener al menos 3 caracteres',
        ]);
        if ($v->fails()) {
            $msg = $v->errors()->first('name') ?: 'Datos inválidos';
            return response()->json(['success' => false, 'message' => $msg], 400);
        }
        $name = $request->input('name');
        $colorCode = $request->input('color_code');
        $extras = $request->only(['allow_saturday', 'allow_sunday', 'default_priority']);
        if (CalendarActivity::where('name', $name)->where('role_group_id', $roleGroupId)->where('id', '!=', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Ya existe una actividad con ese nombre'], 400);
        }
        $activity = $this->activityService->updateActivity($id, $name, $colorCode, $extras, $roleGroupId);
        if (!$activity) {
            return response()->json(['success' => false, 'message' => 'Actividad no encontrada'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $activity->id,
                'name' => $activity->name,
                'orden' => $activity->orden ?? 0,
                'color_code' => $activity->color_code,
                'allow_saturday' => (bool) $activity->allow_saturday,
                'allow_sunday' => (bool) $activity->allow_sunday,
                'default_priority' => (int) ($activity->default_priority ?? 0),
            ],
            'message' => 'Actividad actualizada',
        ]);
    }

    /**
     * DELETE /api/calendar/activity-catalog/{id} - Eliminar actividad del catálogo (solo si no está en uso)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$this->permissionService->canManageActivities(JWTAuth::parseToken()->authenticate())) {
            return response()->json(['success' => false, 'message' => 'No tienes permiso para eliminar del catálogo'], 403);
        }
        $user = JWTAuth::parseToken()->authenticate();
        $roleGroupId = $this->resolveRoleGroupIdForUser($request, $user);
        if ($roleGroupId === null) {
            return response()->json(['success' => false, 'message' => 'El usuario no tiene grupo de calendario asignado'], 400);
        }
        try {
            $deleted = $this->activityService->deleteActivity($id, $roleGroupId);
            if (!$deleted) {
                $activity = CalendarActivity::where('id', $id)->where('role_group_id', $roleGroupId)->first();
                if (!$activity) {
                    return response()->json(['success' => false, 'message' => 'Actividad no encontrada'], 404);
                }
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar porque está siendo usada en eventos',
                ], 400);
            }
            return response()->json(['success' => true, 'message' => 'Actividad eliminada del catálogo']);
        } catch (\Exception $e) {
            Log::error('CalendarActivityController@destroy: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al eliminar actividad'], 500);
        }
    }

    /**
     * Usuarios con perfil Coordinación o Documentación (para dropdown "Responsable")
     */
    public function getResponsibleUsers(): JsonResponse
    {
        try {
            $users = Usuario::whereHas('grupo', function ($q) {
                $q->whereIn('No_Grupo', [Usuario::ROL_COORDINACION, Usuario::ROL_DOCUMENTACION]);
            })
                ->where('Nu_Estado', 1)
                ->orderBy('No_Nombres_Apellidos')
                ->get(['ID_Usuario', 'No_Usuario', 'No_Nombres_Apellidos', 'Txt_Email', 'ID_Grupo', 'Txt_Foto']);
            $data = $users->map(fn ($u) => [
                'id'    => $u->ID_Usuario,
                'label' => $u->No_Nombres_Apellidos ?: $u->No_Usuario,
                'email' => $u->Txt_Email,
                'avatar' => $this->generateImageUrl($u->Txt_Foto ?? null),
            ]);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('CalendarActivityController@getResponsibleUsers: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al listar responsables'], 500);
        }
    }

    /**
     * Consolidados para dropdown (contenedores creados)
     */
    public function getConsolidadosDropdown(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year', date('Y'));
            $cargas = Contenedor::where('empresa', '!=', 1)
                ->where(function ($q) use ($year) {
                    $q->whereYear('f_inicio', $year)->orWhereNull('f_inicio');
                })
                ->where('estado_china', '!=', Contenedor::CONTEDOR_CERRADO)
                ->orderByRaw('CAST(carga AS UNSIGNED) DESC')
                ->get(['id', 'carga', 'f_inicio']);
            $data = $cargas->map(function ($c) {
                $label = 'Contenedor #' . $c->carga;
                if ($c->f_inicio) {
                    $label .= ' - ' . $c->f_inicio->format('Y');
                }
                return ['value' => $c->id, 'label' => $label];
            });
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('CalendarActivityController@getConsolidadosDropdown: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al listar consolidados'], 500);
        }
    }

    /**
     * Crear evento desde formulario "Nueva actividad"
     * Body: activity_id?, name?, start_date, end_date, responsible_user_ids[], contenedor_id?, notes?
     */
    public function storeActivityEvent(Request $request): JsonResponse
    {
        $request->validate([
            'calendar_id'           => 'required|integer|exists:calendars,id',
            'start_date'            => 'required|date',
            'end_date'              => 'required|date|after_or_equal:start_date',
            'activity_id'           => 'nullable|integer|exists:calendar_activities,id',
            'name'                  => 'nullable|string|max:255',
            'responsible_user_ids'  => 'nullable|array',
            'responsible_user_ids.*'=> 'integer|exists:usuario,ID_Usuario',
            'contenedor_id'         => 'nullable|integer|exists:carga_consolidada_contenedor,id',
            'notes'                 => 'nullable|string',
        ]);
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $payload = $request->only([
                'activity_id', 'name', 'start_date', 'end_date',
                'responsible_user_ids', 'contenedor_id', 'notes',
            ]);
            $calendar = Calendar::find((int) $request->calendar_id);
            if ($calendar && !$this->groupUsaConsolidado($calendar, $user ? $user->getIdUsuario() : null)) {
                $payload['contenedor_id'] = null;
            }
            $event = $this->eventService->createActivityEvent(
                (int) $request->calendar_id,
                $payload,
                $user ? $user->getIdUsuario() : null
            );
            return response()->json(['success' => true, 'data' => $event], 201);
        } catch (\Exception $e) {
            Log::error('CalendarActivityController@storeActivityEvent: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener o crear calendario del usuario actual (para formulario)
     */
    public function getOrCreateMyCalendar(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->getIdUsuario();
            $calendar = Calendar::firstOrCreate(
                ['user_id' => $userId],
                ['user_id' => $userId]
            );
            return response()->json(['success' => true, 'data' => $calendar]);
        } catch (\Exception $e) {
            Log::error('CalendarActivityController@getOrCreateMyCalendar: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener calendario'], 500);
        }
    }

    // --- Especificación API: POST/PUT/DELETE /activities (eventos) ---

    /**
     * POST /api/calendar/activities - Crear actividad (evento). Solo Jefe.
     */
    public function storeActivity(Request $request): JsonResponse
    {
        if (!$this->permissionService->canManageActivities(JWTAuth::parseToken()->authenticate())) {
            return response()->json(['success' => false, 'message' => 'No tienes permiso para crear actividades'], 403);
        }
        $v = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'activity_id' => 'nullable|integer|exists:calendar_activities,id',
            'priority' => 'nullable|integer|in:0,1,2',
            'contenedor_id' => 'nullable|integer|exists:carga_consolidada_contenedor,id',
            'notes' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'responsable_ids' => 'nullable|array|max:2',
            'responsable_ids.*' => 'integer|exists:usuario,ID_Usuario',
        ]);
        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $calendar = Calendar::firstOrCreate(
                ['user_id' => $user->getIdUsuario()],
                ['user_id' => $user->getIdUsuario()]
            );
            $data = $request->only(['name', 'activity_id', 'priority', 'contenedor_id', 'notes', 'start_date', 'end_date']);
            $data['responsable_ids'] = $request->input('responsable_ids', []);
            if (!$this->groupUsaConsolidado($calendar, $user->getIdUsuario())) {
                $data['contenedor_id'] = null;
            }
            $event = $this->eventService->createActivityEvent($calendar->id, $data, $user->getIdUsuario());
            $formatted = $this->eventService->formatEventForResponse($event);
            return response()->json([
                'success' => true,
                'data' => $formatted,
                'message' => 'Actividad creada correctamente',
            ], 201);
        } catch (\Exception $e) {
            Log::error('CalendarActivityController@storeActivity: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/calendar/activities/{id} - Actualizar actividad. Solo Jefe.
     */
    public function updateActivity(Request $request, int $id): JsonResponse
    {
        if (!$this->permissionService->canManageActivities(JWTAuth::parseToken()->authenticate())) {
            return response()->json(['success' => false, 'message' => 'No tienes permiso para editar actividades'], 403);
        }
        $user = JWTAuth::parseToken()->authenticate();
        $data = $request->only(['name', 'activity_id', 'priority', 'contenedor_id', 'notes', 'start_date', 'end_date', 'responsable_ids']);
        $event = CalendarEvent::find($id);
        if ($event && !$this->groupUsaConsolidado($event->calendar, $user->getIdUsuario())) {
            $data['contenedor_id'] = null;
        }
        $event = $this->eventService->updateEvent($id, $user->getIdUsuario(), $data, true);
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Actividad no encontrada'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $this->eventService->formatEventForResponse($event),
            'message' => 'Actividad actualizada correctamente',
        ]);
    }

    /**
     * DELETE /api/calendar/activities/{id} - Eliminar actividad (soft). Solo Jefe.
     */
    /**
     * DELETE /api/calendar/activities/{id}
     * Elimina una actividad (solo soft delete: se marca deleted_at, no se borra de la BD).
     */
    public function destroyActivity(int $id): JsonResponse
    {
        if (!$this->permissionService->canManageActivities(JWTAuth::parseToken()->authenticate())) {
            return response()->json(['success' => false, 'message' => 'No tienes permiso para eliminar actividades'], 403);
        }
        $user = JWTAuth::parseToken()->authenticate();
        $deleted = $this->eventService->deleteEvent($id, $user->getIdUsuario(), true);
        if (!$deleted) {
            return response()->json(['success' => false, 'message' => 'Actividad no encontrada'], 404);
        }
        return response()->json(['success' => true, 'message' => 'Actividad eliminada correctamente']);
    }

    /**
     * PUT /api/calendar/charges/{charge_id}/status - Estado del responsable. Jefe: cualquiera; Coord/Doc: solo el propio.
     */
    public function updateChargeStatus(Request $request, int $chargeId): JsonResponse
    {
        $request->validate(['status' => 'required|in:PENDIENTE,PROGRESO,COMPLETADO']);
        $user = JWTAuth::parseToken()->authenticate();
        $charge = CalendarEventCharge::find($chargeId);
        if (!$charge) {
            return response()->json(['success' => false, 'message' => 'Carga no encontrada'], 404);
        }
        $canChangeAny = $this->permissionService->canChangeAnyChargeStatus($user);
        $isOwn = $charge->user_id === $user->getIdUsuario();
        if (!$canChangeAny && !$isOwn) {
            return response()->json(['success' => false, 'message' => 'No puedes cambiar el estado de otro responsable'], 403);
        }
        $this->eventService->updateChargeStatus($chargeId, $user->getIdUsuario(), $request->status, $user->getIdUsuario());
        return response()->json(['success' => true, 'message' => 'Estado actualizado correctamente']);
    }

    /**
     * PUT /api/calendar/activities/{id}/priority - Solo Jefe.
     */
    public function updateEventPriority(Request $request, int $id): JsonResponse
    {
        if (!$this->permissionService->canManageActivities(JWTAuth::parseToken()->authenticate())) {
            return response()->json(['success' => false, 'message' => 'Solo el Jefe de Importaciones puede cambiar la prioridad'], 403);
        }
        $request->validate(['priority' => 'required|integer|in:0,1,2']);
        $user = JWTAuth::parseToken()->authenticate();
        $event = $this->eventService->updateEventPriority($id, $user->getIdUsuario(), (int) $request->priority);
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Actividad no encontrada'], 404);
        }
        return response()->json(['success' => true, 'message' => 'Prioridad actualizada correctamente']);
    }

    /**
     * PUT /api/calendar/charges/{charge_id}/notes
     */
    public function updateChargeNotes(Request $request, int $chargeId): JsonResponse
    {
        $request->validate(['notes' => 'nullable|string']);
        $user = JWTAuth::parseToken()->authenticate();
        $charge = $this->eventService->updateChargeNotes($chargeId, $user->getIdUsuario(), $request->input('notes', ''));
        if (!$charge) {
            return response()->json(['success' => false, 'message' => 'Carga no encontrada'], 404);
        }
        return response()->json(['success' => true, 'message' => 'Notas actualizadas correctamente']);
    }

    /**
     * PUT /api/calendar/activities/{id}/notes
     */
    public function updateEventNotes(Request $request, int $id): JsonResponse
    {
        $request->validate(['notes' => 'nullable|string']);
        $user = JWTAuth::parseToken()->authenticate();
        $event = $this->eventService->updateEventNotes($id, $user->getIdUsuario(), $request->input('notes', ''));
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Actividad no encontrada'], 404);
        }
        return response()->json(['success' => true, 'message' => 'Notas actualizadas correctamente']);
    }

    /**
     * GET /api/calendar/responsables - Formato spec (id, nombre, email, avatar, color).
     * Query opcional: role_group_id. Si viene, devuelve solo miembros de ese grupo (validando que el usuario pertenezca).
     * Si no, usa el primer grupo del usuario o fallback legacy.
     */
    public function getResponsables(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $calendar = Calendar::where('user_id', $user->getIdUsuario())->first();
            $calendarId = $calendar !== null ? $calendar->id : null;
            $colorByUserId = $calendarId
                ? CalendarUserColorConfig::where('calendar_id', $calendarId)->get()->keyBy('user_id')
                : collect();

            $roleGroupId = $request->input('role_group_id');
            $roleGroupId = $roleGroupId !== null && $roleGroupId !== '' ? (int) $roleGroupId : null;
            $member = CalendarRoleGroupMember::where('user_id', $user->getIdUsuario())->first();
            if ($roleGroupId !== null) {
                if (!$this->permissionService->userBelongsToRoleGroup($user, $roleGroupId)) {
                    return response()->json(['success' => false, 'message' => 'No perteneces a ese grupo de calendario'], 403);
                }
                $member = CalendarRoleGroupMember::where('user_id', $user->getIdUsuario())
                    ->where('role_group_id', $roleGroupId)->first();
            }

            if ($member) {
                $userIds = CalendarRoleGroupMember::where('role_group_id', $member->role_group_id)
                    ->pluck('user_id')
                    ->unique()
                    ->values()
                    ->all();
                $users = Usuario::whereIn('ID_Usuario', $userIds)
                    ->where('Nu_Estado', 1)
                    ->orderBy('No_Nombres_Apellidos')
                    ->get(['ID_Usuario', 'No_Usuario', 'No_Nombres_Apellidos', 'Txt_Email', 'Txt_Foto']);
            } else {
                // Sin grupo: fallback a roles legacy (Coordinación, Documentación, Jefe Importación)
                $users = Usuario::whereHas('grupo', fn ($q) => $q->whereIn('No_Grupo', [Usuario::ROL_COORDINACION, Usuario::ROL_DOCUMENTACION, Usuario::ROL_JEFE_IMPORTACION]))
                    ->where('Nu_Estado', 1)
                    ->orderBy('No_Nombres_Apellidos')
                    ->get(['ID_Usuario', 'No_Usuario', 'No_Nombres_Apellidos', 'Txt_Email', 'Txt_Foto']);
            }

            $data = $users->map(function ($u) use ($colorByUserId) {
                $color = optional($colorByUserId->get($u->ID_Usuario))->color_code;
                return [
                    'id' => $u->ID_Usuario,
                    'nombre' => $u->No_Nombres_Apellidos ?: $u->No_Usuario,
                    'email' => $u->Txt_Email ?? null,
                    'avatar' => $this->generateImageUrl($u->Txt_Foto ?? null),
                    'color' => $color,
                ];
            });
            return response()->json(['success' => true, 'data' => $data, 'message' => 'Responsables obtenidos correctamente']);
        } catch (\Exception $e) {
            Log::error('CalendarActivityController@getResponsables: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al listar responsables'], 500);
        }
    }

    /**
     * POST /api/calendar/activities/{id}/responsables - Asignar responsable. Solo Jefe. Máx 2.
     */
    public function addResponsable(Request $request, int $id): JsonResponse
    {
        if (!$this->permissionService->canManageResponsables(JWTAuth::parseToken()->authenticate())) {
            return response()->json(['success' => false, 'message' => 'Solo el Jefe de Importaciones puede asignar responsables'], 403);
        }
        $request->validate(['user_id' => 'required|integer|exists:usuario,ID_Usuario']);
        $user = JWTAuth::parseToken()->authenticate();
        $charge = $this->eventService->addResponsable($id, (int) $request->user_id, $user->getIdUsuario());
        if (!$charge) {
            $count = CalendarEventCharge::where('calendar_event_id', $id)->count();
            if ($count >= 2) {
                return response()->json(['success' => false, 'message' => 'La actividad ya tiene el máximo de 2 responsables'], 400);
            }
            return response()->json(['success' => false, 'message' => 'El usuario ya está asignado a esta actividad'], 400);
        }
        return response()->json(['success' => true, 'message' => 'Responsable asignado correctamente']);
    }

    /**
     * DELETE /api/calendar/activities/{id}/responsables/{user_id} - Quitar responsable. Solo Jefe.
     */
    public function removeResponsable(int $id, int $userId): JsonResponse
    {
        if (!$this->permissionService->canManageResponsables(JWTAuth::parseToken()->authenticate())) {
            return response()->json(['success' => false, 'message' => 'Solo el Jefe de Importaciones puede remover responsables'], 403);
        }
        $requestUserId = JWTAuth::parseToken()->authenticate()->getIdUsuario();
        $removed = $this->eventService->removeResponsable($id, $userId, $requestUserId);
        if (!$removed) {
            return response()->json(['success' => false, 'message' => 'Responsable no encontrado en esta actividad'], 404);
        }
        return response()->json(['success' => true, 'message' => 'Responsable removido correctamente']);
    }

    /**
     * GET /api/calendar/colors - Configuración de colores por usuario
     */
    public function getColors(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            //not filter by user id
            $calendarId = Calendar::value('id');
            if (!$calendarId) {
                return response()->json(['success' => true, 'data' => [], 'message' => 'Configuración de colores obtenida']);
            }
            $configs = CalendarUserColorConfig::where('calendar_id', $calendarId)->with('user')->get();
            $data = $configs->map(fn ($c) => [
                'id' => $c->id,
                'calendar_id' => $c->calendar_id,
                'user_id' => $c->user_id,
                'color_code' => $c->color_code,
                'user' => $c->user ? ['id' => $c->user->ID_Usuario, 'nombre' => $c->user->No_Nombres_Apellidos ?: $c->user->No_Usuario] : null,
            ]);
            return response()->json(['success' => true, 'data' => $data, 'message' => 'Configuración de colores obtenida']);
        } catch (\Exception $e) {
            Log::error('CalendarActivityController@getColors: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener colores'], 500);
        }
    }

    /**
     * PUT /api/calendar/colors - Actualizar color. Solo Jefe.
     */
    public function updateColor(Request $request): JsonResponse
    {
        if (!$this->permissionService->canManageColors(JWTAuth::parseToken()->authenticate())) {
            return response()->json(['success' => false, 'message' => 'Solo el Jefe de Importaciones puede configurar colores'], 403);
        }
        $request->validate(['user_id' => 'required|integer|exists:usuario,ID_Usuario', 'color_code' => 'required|string|max:20']);
        $user = JWTAuth::parseToken()->authenticate();
        $calendarId = Calendar::where('user_id', $user->getIdUsuario())->value('id');
        if (!$calendarId) {
            $cal = Calendar::firstOrCreate(['user_id' => $user->getIdUsuario()], ['user_id' => $user->getIdUsuario()]);
            $calendarId = $cal->id;
        }
        $config = CalendarUserColorConfig::updateOrCreate(
            ['calendar_id' => $calendarId, 'user_id' => $request->user_id],
            ['color_code' => $request->color_code]
        );
        return response()->json(['success' => true, 'message' => 'Color actualizado correctamente']);
    }

    /**
     * GET /api/calendar/contenedores - Lista consolidados (id, nombre, codigo)
     */
    public function getContenedores(): JsonResponse
    {
        try {
            $year = request()->input('year', date('Y'));
            $contenedores = Contenedor::where('empresa', '!=', 1)
                ->where('estado_documentacion', '!=', Contenedor::CONTEDOR_CERRADO)
                ->orderByRaw('COALESCE(YEAR(f_inicio), 2025) ASC, CAST(carga AS UNSIGNED) ASC')
                ->get(['id', 'carga', 'f_inicio']);
            $data = $contenedores->map(function ($c) {
                $anio = $c->f_inicio ? $c->f_inicio->format('Y') : '2025';
                $nombre = '#' . $c->carga . ' - ' . $anio;
                $codigo = 'CONT-' . $anio . '-' . str_pad((string) $c->id, 3, '0', STR_PAD_LEFT);
                return ['id' => $c->id, 'nombre' => $nombre, 'codigo' => $codigo];
            });
            return response()->json(['success' => true, 'data' => $data, 'message' => 'Contenedores obtenidos correctamente']);
        } catch (\Exception $e) {
            Log::error('CalendarActivityController@getContenedores: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al listar contenedores'], 500);
        }
    }

    /**
     * GET /api/calendar/progress - Progreso del equipo. Solo Jefe.
     * Obtiene el usuario del JWT, su grupo de calendario y restringe datos a los miembros de ese grupo.
     */
    public function getProgress(Request $request): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$this->permissionService->canViewTeamProgress($user)) {
            return response()->json(['success' => false, 'message' => 'No tienes permiso para ver el progreso del equipo'], 403);
        }

        // role_group_id desde query: si viene, validar que el usuario pertenezca y usarlo; si no, usar su primera membresía
        $roleGroupId = $request->input('role_group_id');
        $roleGroupId = $roleGroupId !== null && $roleGroupId !== '' ? (int) $roleGroupId : null;
        if ($roleGroupId !== null && !$this->permissionService->userBelongsToRoleGroup($user, $roleGroupId)) {
            return response()->json(['success' => false, 'message' => 'No perteneces a ese grupo de calendario'], 403);
        }
        if ($roleGroupId === null) {
            $membership = CalendarRoleGroupMember::where('user_id', $user->getIdUsuario())->first();
            if ($membership !== null) {
                $roleGroupId = (int) $membership->role_group_id;
            }
        }

        $roleGroupMemberIds = null;
        if ($roleGroupId !== null) {
            $roleGroupMemberIds = CalendarRoleGroupMember::where('role_group_id', $roleGroupId)
                ->pluck('user_id')
                ->unique()
                ->values()
                ->all();
        }

        // Parsear responsable_ids (array o CSV)
        $responsableIds = null;
        $rawIds = $request->input('responsable_ids');
        if (is_array($rawIds)) {
            $responsableIds = array_values(array_filter(array_map('intval', $rawIds)));
        } elseif (is_string($rawIds) && $rawIds !== '') {
            $responsableIds = array_values(array_filter(array_map('intval', explode(',', $rawIds))));
        }
        if ($responsableIds !== null && empty($responsableIds)) {
            $responsableIds = null;
        }

        // Parsear contenedor_ids (array o CSV)
        $contenedorIds = null;
        $rawContenedores = $request->input('contenedor_ids');
        if (is_array($rawContenedores)) {
            $contenedorIds = array_values(array_filter(array_map('intval', $rawContenedores)));
        } elseif (is_string($rawContenedores) && $rawContenedores !== '') {
            $contenedorIds = array_values(array_filter(array_map('intval', explode(',', $rawContenedores))));
        }
        if ($contenedorIds !== null && empty($contenedorIds)) {
            $contenedorIds = null;
        }

        $data = $this->eventService->getProgress(
            $request->input('start_date'),
            $request->input('end_date'),
            null,
            $responsableIds,
            $contenedorIds,
            $request->input('status'),
            $request->input('priority') !== null ? (int) $request->input('priority') : null,
            $roleGroupMemberIds
        );
        return response()->json(['success' => true, 'data' => $data, 'message' => 'Progreso obtenido correctamente']);
    }

    /**
     * GET /api/calendar/charges/{chargeId}/tracking - Historial de cambios de estado de un charge.
     * Jefe: todos; Coordinación/Documentación: solo sus propios charges.
     */
    public function getChargeTracking(int $chargeId): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $userId = $user->getIdUsuario();
        $canSeeAll = $this->permissionService->isJefeImportaciones($user);
        $data = $this->eventService->getTrackingForCharge($chargeId, $userId, $canSeeAll);
        if ($data === null) {
            return response()->json(['success' => false, 'message' => 'Charge no encontrado'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Historial obtenido correctamente',
        ]);
    }

    /**
     * GET /api/calendar/activities/{activityId}/tracking - Historial de cambios de estado de todos los charges de una actividad.
     * Jefe: todas; Coordinación/Documentación: solo actividades donde está asignado.
     */
    public function getActivityTracking(int $activityId): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $userId = $user->getIdUsuario();
        $canSeeAll = $this->permissionService->isJefeImportaciones($user);
        $data = $this->eventService->getTrackingForActivity($activityId, $userId, $canSeeAll);
        if ($data === null) {
            return response()->json(['success' => false, 'message' => 'Actividad no encontrada'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Historial de actividad obtenido correctamente',
        ]);
    }

    /**
     * POST /api/calendar/activity-catalog/reorder - Reordenar catálogo. Solo Jefe.
     */
    public function reorderCatalog(Request $request): JsonResponse
    {
        if (!$this->permissionService->canManageActivities(JWTAuth::parseToken()->authenticate())) {
            return response()->json(['success' => false, 'message' => 'Sin permiso para reordenar el catálogo'], 403);
        }
        $user = JWTAuth::parseToken()->authenticate();
        $roleGroupId = $this->resolveRoleGroupIdForUser($request, $user);
        if ($roleGroupId === null) {
            return response()->json(['success' => false, 'message' => 'El usuario no tiene grupo de calendario asignado'], 400);
        }
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:calendar_activities,id']);
        $ids = $request->input('ids');
        $countInGroup = CalendarActivity::whereIn('id', $ids)->where('role_group_id', $roleGroupId)->count();
        if ($countInGroup !== count($ids)) {
            return response()->json(['success' => false, 'message' => 'Solo se pueden reordenar actividades de tu grupo'], 403);
        }
        try {
            $this->activityService->reorderActivities($ids, $roleGroupId);
            return response()->json(['success' => true, 'message' => 'Catálogo reordenado correctamente']);
        } catch (\Exception $e) {
            Log::error('CalendarActivityController@reorderCatalog: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al reordenar'], 500);
        }
    }

    /**
     * GET /api/calendar/consolidado-colors - Colores por consolidado del usuario autenticado
     */
    public function getConsolidadoColors(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $calendar = Calendar::where('user_id', $user->getIdUsuario())->first();
            if (!$calendar) {
                return response()->json(['success' => true, 'data' => [], 'message' => 'Sin colores de consolidado']);
            }

            // Resolver grupo de rol del calendario (o del usuario)
            $roleGroupId = $calendar->role_group_id;
            if (!$roleGroupId) {
                $member = CalendarRoleGroupMember::where('user_id', $user->getIdUsuario())->first();
                if ($member) {
                    $roleGroupId = $member->role_group_id;
                }
            }

            if (!$roleGroupId) {
                return response()->json(['success' => true, 'data' => [], 'message' => 'Sin colores de consolidado']);
            }

            $configs = CalendarConsolidadoColorConfig::where('role_group_id', $roleGroupId)->get();
            $data = $configs->map(fn ($c) => [
                'id' => $c->id,
                'calendar_id' => $c->calendar_id,
                'role_group_id' => $c->role_group_id,
                'contenedor_id' => $c->contenedor_id,
                'color_code' => $c->color_code,
            ]);
            return response()->json(['success' => true, 'data' => $data, 'message' => 'Colores de consolidado obtenidos']);
        } catch (\Exception $e) {
            Log::error('CalendarActivityController@getConsolidadoColors: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener colores de consolidado'], 500);
        }
    }

    /**
     * PUT /api/calendar/consolidado-colors - Guardar colores de consolidados en batch. Solo Jefe.
     * Body: { items: [{ contenedor_id, color_code }, ...] }
     */
    public function updateConsolidadoColor(Request $request): JsonResponse
    {
        if (!$this->permissionService->canManageColors(JWTAuth::parseToken()->authenticate())) {
            return response()->json(['success' => false, 'message' => 'Solo el Jefe de Importaciones puede configurar colores'], 403);
        }
        $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.contenedor_id'  => 'required|integer|exists:carga_consolidada_contenedor,id',
            'items.*.color_code'     => 'required|string|max:20',
        ]);
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $calendar = Calendar::firstOrCreate(
                ['user_id' => $user->getIdUsuario()],
                ['user_id' => $user->getIdUsuario()]
            );

            // Grupo de rol asociado al calendario / usuario
            $roleGroupId = $calendar->role_group_id;
            if (!$roleGroupId) {
                $member = CalendarRoleGroupMember::where('user_id', $user->getIdUsuario())->first();
                if ($member) {
                    $roleGroupId = $member->role_group_id;
                    $calendar->role_group_id = $roleGroupId;
                    $calendar->save();
                }
            }

            if (!$roleGroupId) {
                return response()->json(['success' => false, 'message' => 'El usuario no tiene grupo de calendario asignado'], 400);
            }

            foreach ($request->input('items') as $item) {
                CalendarConsolidadoColorConfig::updateOrCreate(
                    [
                        'role_group_id' => $roleGroupId,
                        'contenedor_id' => $item['contenedor_id'],
                    ],
                    [
                        'calendar_id' => $calendar->id,
                        'color_code' => $item['color_code'],
                    ]
                );
            }
            return response()->json(['success' => true, 'message' => 'Colores de consolidado actualizados correctamente']);
        } catch (\Exception $e) {
            Log::error('CalendarActivityController@updateConsolidadoColor: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al actualizar colores de consolidado'], 500);
        }
    }

    /**
     * Resuelve el role_group_id para el usuario: query role_group_id (validando membresía) o primera membresía.
     */
    private function resolveRoleGroupIdForUser(Request $request, $user): ?int
    {
        $roleGroupId = $request->input('role_group_id');
        $roleGroupId = $roleGroupId !== null && $roleGroupId !== '' ? (int) $roleGroupId : null;
        if ($roleGroupId !== null && !$this->permissionService->userBelongsToRoleGroup($user, $roleGroupId)) {
            return null;
        }
        if ($roleGroupId === null) {
            $member = CalendarRoleGroupMember::where('user_id', $user->getIdUsuario())->first();
            $roleGroupId = $member ? (int) $member->role_group_id : null;
        }
        return $roleGroupId;
    }

    /**
     * Indica si el grupo de calendario del usuario usa consolidado.
     * Si el calendario no tiene role_group_id, se obtiene de la membresía del usuario.
     */
    private function groupUsaConsolidado(?Calendar $calendar, ?int $userId): bool
    {
        $roleGroupId = null;
        if ($calendar && $calendar->role_group_id) {
            $roleGroupId = $calendar->role_group_id;
        } elseif ($userId) {
            $roleGroupId = CalendarRoleGroupMember::where('user_id', $userId)->value('role_group_id');
        }
        if (!$roleGroupId) {
            return true;
        }
        $group = CalendarRoleGroup::find($roleGroupId);

        return $group ? (bool) $group->usa_consolidado : true;
    }
}
