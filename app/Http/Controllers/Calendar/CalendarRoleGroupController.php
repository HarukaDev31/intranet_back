<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Models\Calendar\CalendarRoleGroup;
use App\Models\Calendar\CalendarRoleGroupMember;
use App\Models\Calendar\CalendarRoleGroupConfig;
use App\Models\Usuario;
use App\Services\Calendar\CalendarPermissionService;
use App\Services\Calendar\CalendarResponsablesCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class CalendarRoleGroupController extends Controller
{
    protected CalendarPermissionService $permissionService;

    public function __construct(CalendarPermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * GET /api/calendar/my-role-groups
     * Devuelve los grupos de calendario a los que pertenece el usuario autenticado.
     * Pensado para poblar el selector de calendarios en la vista principal.
     */
    public function myRoleGroups(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->getIdUsuario();

            $groupIds = CalendarRoleGroupMember::where('user_id', $userId)
                ->pluck('role_group_id')
                ->unique()
                ->values()
                ->all();

            if (empty($groupIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'El usuario no pertenece a ningún grupo de calendario',
                ]);
            }

            $groups = CalendarRoleGroup::whereIn('id', $groupIds)
                ->orderBy('name')
                ->get();

            $data = $groups->map(function (CalendarRoleGroup $g) use ($userId) {
                $membership = $g->members()->where('user_id', $userId)->first();

                return [
                    'id' => $g->id,
                    'name' => $g->name,
                    'code' => $g->code,
                    'usa_consolidado' => (bool) $g->usa_consolidado,
                    'is_active' => (bool) $g->is_active,
                    'role_type' => $membership ? $membership->role_type : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Grupos de calendario del usuario obtenidos correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('CalendarRoleGroupController@myRoleGroups: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos de calendario del usuario',
            ], 500);
        }
    }

    /**
     * Verifica que el usuario autenticado tenga permisos de administración de grupos de rol.
     */
    protected function ensureCanManage(): ?JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$this->permissionService->canManageActivities($user)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para administrar grupos de roles de calendario',
            ], 403);
        }

        return null;
    }

    /**
     * GET /api/calendar/users - Lista de usuarios de la intranet para agregar a grupos de roles.
     * Requiere permisos de administración. Parámetro opcional: search (nombre, usuario o email).
     */
    public function getIntranetUsers(Request $request): JsonResponse
    {
        if ($resp = $this->ensureCanManage()) {
            return $resp;
        }

        try {
            $query = Usuario::where('Nu_Estado', 1)
            ->whereNull('ID_Entidad')
                ->orderBy('No_Nombres_Apellidos');

            if ($request->filled('search')) {
                $term = trim($request->input('search'));
                $query->where(function ($q) use ($term) {
                    $q->where('No_Nombres_Apellidos', 'like', '%' . $term . '%')
                        ->orWhere('No_Usuario', 'like', '%' . $term . '%')
                        ->orWhere('Txt_Email', 'like', '%' . $term . '%');
                });
            }

            $users = $query->limit(250)->get(['ID_Usuario', 'No_Usuario', 'No_Nombres_Apellidos', 'Txt_Email']);

            $data = $users->map(fn ($u) => [
                'id' => $u->ID_Usuario,
                'nombre' => $u->No_Nombres_Apellidos ?: $u->No_Usuario,
                'email' => $u->Txt_Email ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Usuarios obtenidos correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('CalendarRoleGroupController@getIntranetUsers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al listar usuarios',
            ], 500);
        }
    }

    /**
     * GET /api/calendar/role-groups
     */
    public function index(): JsonResponse
    {
        try {
            $groups = CalendarRoleGroup::orderBy('name')->get();

            $data = $groups->map(function (CalendarRoleGroup $g) {
                return [
                    'id' => $g->id,
                    'name' => $g->name,
                    'code' => $g->code,
                    'usa_consolidado' => (bool) $g->usa_consolidado,
                    'is_active' => (bool) $g->is_active,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Grupos de roles obtenidos correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('CalendarRoleGroupController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al listar grupos de roles',
            ], 500);
        }
    }

    /**
     * POST /api/calendar/role-groups
     */
    public function store(Request $request): JsonResponse
    {
        if ($resp = $this->ensureCanManage()) {
            return $resp;
        }

        $v = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100|unique:calendar_role_groups,code',
            'usa_consolidado' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors(),
            ], 422);
        }

        try {
            $group = CalendarRoleGroup::create([
                'name' => $request->input('name'),
                'code' => $request->input('code'),
                'usa_consolidado' => $request->boolean('usa_consolidado', true),
                'is_active' => $request->boolean('is_active', true),
            ]);

            return response()->json([
                'success' => true,
                'data' => $group,
                'message' => 'Grupo de roles creado correctamente',
            ], 201);
        } catch (\Exception $e) {
            Log::error('CalendarRoleGroupController@store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear grupo de roles',
            ], 500);
        }
    }

    /**
     * PUT /api/calendar/role-groups/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($resp = $this->ensureCanManage()) {
            return $resp;
        }

        $group = CalendarRoleGroup::find($id);
        if (!$group) {
        return response()->json(['success' => false, 'message' => 'Grupo no encontrado'], 404);
        }

        $v = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100|unique:calendar_role_groups,code,' . $group->id,
            'usa_consolidado' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors(),
            ], 422);
        }

        try {
            $group->name = $request->input('name');
            $group->code = $request->input('code');
            if ($request->has('usa_consolidado')) {
                $group->usa_consolidado = $request->boolean('usa_consolidado');
            }
            if ($request->has('is_active')) {
                $group->is_active = $request->boolean('is_active');
            }
            $group->save();

            return response()->json([
                'success' => true,
                'data' => $group,
                'message' => 'Grupo de roles actualizado correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('CalendarRoleGroupController@update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar grupo de roles',
            ], 500);
        }
    }

    /**
     * DELETE /api/calendar/role-groups/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        if ($resp = $this->ensureCanManage()) {
            return $resp;
        }

        $group = CalendarRoleGroup::find($id);
        if (!$group) {
            return response()->json(['success' => false, 'message' => 'Grupo no encontrado'], 404);
        }

        try {
            $group->delete();
            return response()->json([
                'success' => true,
                'message' => 'Grupo de roles eliminado correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('CalendarRoleGroupController@destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar grupo de roles',
            ], 500);
        }
    }

    /**
     * GET /api/calendar/role-groups/{id}/members
     */
    public function members(int $id): JsonResponse
    {
        try {
            $group = CalendarRoleGroup::find($id);
            if (!$group) {
                return response()->json(['success' => false, 'message' => 'Grupo no encontrado'], 404);
            }

            $members = CalendarRoleGroupMember::where('role_group_id', $id)
                ->with('user')
                ->get()
                ->map(function (CalendarRoleGroupMember $m) {
                    return [
                        'id' => $m->id,
                        'user_id' => $m->user_id,
                        'role_type' => $m->role_type,
                        'user' => $m->user ? [
                            'id' => $m->user->ID_Usuario,
                            'nombre' => $m->user->No_Nombres_Apellidos ?: $m->user->No_Usuario,
                            'email' => $m->user->Txt_Email,
                        ] : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $members,
                'message' => 'Miembros obtenidos correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('CalendarRoleGroupController@members: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al listar miembros',
            ], 500);
        }
    }

    /**
     * POST /api/calendar/role-groups/{id}/members
     */
    public function addMember(Request $request, int $id): JsonResponse
    {
        if ($resp = $this->ensureCanManage()) {
            return $resp;
        }

        $group = CalendarRoleGroup::find($id);
        if (!$group) {
            return response()->json(['success' => false, 'message' => 'Grupo no encontrado'], 404);
        }

        $v = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:usuario,ID_Usuario',
            'role_type' => 'required|string|max:50',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors(),
            ], 422);
        }

        try {
            $member = CalendarRoleGroupMember::updateOrCreate(
                [
                    'role_group_id' => $id,
                    'user_id' => $request->input('user_id'),
                ],
                [
                    'role_type' => $request->input('role_type'),
                ]
            );

            CalendarResponsablesCacheService::forgetForRoleGroup($id, (int) $request->input('user_id'));

            return response()->json([
                'success' => true,
                'data' => $member,
                'message' => 'Miembro agregado/actualizado correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('CalendarRoleGroupController@addMember: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar miembro',
            ], 500);
        }
    }

    /**
     * DELETE /api/calendar/role-groups/{id}/members/{memberId}
     */
    public function removeMember(int $id, int $memberId): JsonResponse
    {
        if ($resp = $this->ensureCanManage()) {
            return $resp;
        }

        $member = CalendarRoleGroupMember::where('role_group_id', $id)->where('id', $memberId)->first();
        if (!$member) {
            return response()->json(['success' => false, 'message' => 'Miembro no encontrado'], 404);
        }

        try {
            $userIdsBefore = CalendarRoleGroupMember::where('role_group_id', $id)
                ->pluck('user_id')
                ->unique()
                ->values()
                ->all();
            $removedUserId = (int) $member->user_id;
            $member->delete();
            CalendarResponsablesCacheService::forgetAfterMemberRemoved($id, $removedUserId, $userIdsBefore);

            return response()->json([
                'success' => true,
                'message' => 'Miembro eliminado correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('CalendarRoleGroupController@removeMember: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar miembro',
            ], 500);
        }
    }

    /**
     * GET /api/calendar/role-groups/{id}/config
     */
    public function getConfig(int $id): JsonResponse
    {
        try {
            $group = CalendarRoleGroup::find($id);
            if (!$group) {
                return response()->json(['success' => false, 'message' => 'Grupo no encontrado'], 404);
            }

            $config = CalendarRoleGroupConfig::where('role_group_id', $id)->first();

            return response()->json([
                'success' => true,
                'data' => $config,
                'message' => 'Configuración obtenida correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('CalendarRoleGroupController@getConfig: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener configuración',
            ], 500);
        }
    }

    /**
     * PUT /api/calendar/role-groups/{id}/config
     */
    public function updateConfig(Request $request, int $id): JsonResponse
    {
        if ($resp = $this->ensureCanManage()) {
            return $resp;
        }

        $group = CalendarRoleGroup::find($id);
        if (!$group) {
            return response()->json(['success' => false, 'message' => 'Grupo no encontrado'], 404);
        }

        $v = Validator::make($request->all(), [
            'color_prioridad' => 'nullable|string|max:20',
            'color_actividad' => 'nullable|string|max:20',
            'color_consolidado' => 'nullable|string|max:20',
            'color_completado' => 'nullable|string|max:20',
            'jefe_color_priority_order' => 'nullable|string|max:120',
            'miembro_color_priority_order' => 'nullable|string|max:120',
            'usa_consolidado' => 'nullable|boolean',
            'show_event_details' => 'nullable|boolean',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors(),
            ], 422);
        }

        try {
            $configData = [
                'color_prioridad' => $request->input('color_prioridad'),
                'color_actividad' => $request->input('color_actividad'),
                'color_consolidado' => $request->input('color_consolidado'),
                'color_completado' => $request->input('color_completado'),
            ];
            if ($request->has('jefe_color_priority_order')) {
                $configData['jefe_color_priority_order'] = $request->input('jefe_color_priority_order');
            }
            if ($request->has('miembro_color_priority_order')) {
                $configData['miembro_color_priority_order'] = $request->input('miembro_color_priority_order');
            }
            if ($request->has('show_event_details')) {
                $configData['show_event_details'] = $request->boolean('show_event_details');
            }
            $config = CalendarRoleGroupConfig::updateOrCreate(
                ['role_group_id' => $id],
                $configData
            );

            if ($request->has('usa_consolidado')) {
                $group->usa_consolidado = $request->boolean('usa_consolidado');
                $group->save();
            }

            return response()->json([
                'success' => true,
                'data' => $config,
                'message' => 'Configuración actualizada correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('CalendarRoleGroupController@updateConfig: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar configuración',
            ], 500);
        }
    }
}

