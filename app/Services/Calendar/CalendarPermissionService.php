<?php

namespace App\Services\Calendar;

use App\Models\Usuario;
use App\Models\Calendar\Calendar;
use App\Models\Calendar\CalendarRoleGroup;
use App\Models\Calendar\CalendarRoleGroupMember;
use Illuminate\Support\Facades\Log;
class CalendarPermissionService
{
    /**
     * Indica si el usuario tiene rol de Gerencia (grupo principal o en cualquier grupo asignado).
     */
    protected function isGerencia(Usuario $user): bool
    {
        if ($user->getNombreGrupo() === Usuario::ROL_GERENCIA) {
            return true;
        }
        $grupos = $user->getAllGrupos();
        return $grupos->contains(fn ($g) => $g && trim((string) $g->No_Grupo) === Usuario::ROL_GERENCIA);
    }

    /**
     * Devuelve el rol del usuario dentro de los grupos de calendario.
     * Posibles valores: JEFE, MIEMBRO, NINGUNO.
     *
     * No dependemos del calendar.role_group_id, sino de la pertenencia
     * del usuario a cualquier grupo de rol de calendario.
     */
    public function getUserCalendarRole(Usuario $user, Calendar $calendar = null): string
    {
        // Gerencia tiene rol de "JEFE" en cualquier calendario
        if ($this->isGerencia($user)) {
            return 'JEFE';
        }

        // Priorizar rol de JEFE si el usuario pertenece a varios grupos; en caso contrario, tomar el primero.
        $member = CalendarRoleGroupMember::where('user_id', $user->getIdUsuario())
            ->orderByRaw("CASE WHEN role_type = 'JEFE' THEN 0 ELSE 1 END")
            ->first();
        if ($member) {
            return $member->role_type;
        }

        return 'NINGUNO';
    }

    public function isJefeImportaciones(Usuario $user): bool
    {
        return $this->getUserCalendarRole($user) === 'JEFE';
    }

    public function isCoordinacionOrDocumentacion(Usuario $user): bool
    {
        $role = $this->getUserCalendarRole($user);
        // A nivel de grupos de calendario, consideramos cualquier miembro del grupo (incluido el Jefe)
        // como parte del equipo para efectos de permisos.
        return in_array($role, ['JEFE', 'MIEMBRO'], true);
    }

    public function canChangeAnyChargeStatus(Usuario $user): bool
    {
        return $this->isJefeImportaciones($user) || $this->isGerencia($user);
    }

    public function canChangeOwnChargeStatus(Usuario $user): bool
    {
        return $this->isJefeImportaciones($user) || $this->isCoordinacionOrDocumentacion($user) || $this->isGerencia($user);
    }

    public function canManageActivities(Usuario $user): bool
    {
        return $this->isJefeImportaciones($user) || $this->isGerencia($user);
    }

    public function canManageResponsables(Usuario $user): bool
    {
        return $this->isJefeImportaciones($user) || $this->isGerencia($user);
    }

    public function canManageColors(Usuario $user): bool
    {
        return $this->isJefeImportaciones($user) || $this->isGerencia($user);
    }

    public function canViewTeamProgress(Usuario $user): bool
    {
        return $this->isJefeImportaciones($user) || $this->isGerencia($user);
    }

    /**
     * Indica si el usuario pertenece al grupo de calendario dado.
     */
    public function userBelongsToRoleGroup(Usuario $user, int $roleGroupId): bool
    {
        return CalendarRoleGroupMember::where('user_id', $user->getIdUsuario())
            ->where('role_group_id', $roleGroupId)
            ->exists();
    }

    /**
     * Devuelve la configuración completa de calendario para el usuario actual.
     * Si se pasa $roleGroupId, se usa ese grupo (el usuario debe ser miembro); si no, se usa el primero al que pertenezca.
     */
    public function getCalendarConfigForUser(Usuario $user, ?int $roleGroupId = null): array
    {
        $calendar = Calendar::firstOrCreate(
            ['user_id' => $user->getIdUsuario()],
            ['user_id' => $user->getIdUsuario()]
        );

        $member = null;
        if ($roleGroupId !== null) {
            $member = CalendarRoleGroupMember::where('user_id', $user->getIdUsuario())
                ->where('role_group_id', $roleGroupId)
                ->first();
        }
        if ($member === null) {
            $member = CalendarRoleGroupMember::where('user_id', $user->getIdUsuario())->first();
        }

        $roleGroup = null;
        $roleType = 'NINGUNO';

        if ($member) {
            $roleGroup = CalendarRoleGroup::with('configs')->find($member->role_group_id);
            $roleType = $member->role_type;

            // Sincronizar calendar.role_group_id con el grupo del miembro (para consistencia)
            if ($roleGroup && $calendar->role_group_id !== $roleGroup->id) {
                $calendar->role_group_id = $roleGroup->id;
                $calendar->save();
            }
        } else {
            // Fallback: si no está en ningún grupo explícito, intentar grupo por defecto
            $defaultGroup = CalendarRoleGroup::where('code', 'CAL_IMPORTACIONES_DEFAULT')->first();
            if ($defaultGroup) {
                $roleGroup = $defaultGroup->load('configs');
                if ($calendar->role_group_id !== $defaultGroup->id) {
                    $calendar->role_group_id = $defaultGroup->id;
                    $calendar->save();
                }
                // Si el usuario está en el grupo por defecto en la tabla de miembros,
                // su rol se resolverá como JEFE/MIEMBRO en llamadas posteriores.
                $member = CalendarRoleGroupMember::where('role_group_id', $defaultGroup->id)
                    ->where('user_id', $user->getIdUsuario())
                    ->first();
                if ($member) {
                    $roleType = $member->role_type;
                }
            }
        }

        $permissions = [
            'canCreateActivity' => $this->canManageActivities($user),
            'canEditActivity' => $this->canManageActivities($user),
            'canDeleteActivity' => $this->canManageActivities($user),
            'canAssignResponsables' => $this->canManageResponsables($user),
            'canEditAnyStatus' => $this->canChangeAnyChargeStatus($user),
            'canEditOwnStatus' => $this->canChangeOwnChargeStatus($user),
            'canEditPriority' => $this->canManageActivities($user),
            'canViewTeamProgress' => $this->canViewTeamProgress($user),
            'canFilterByResponsable' => $this->isCoordinacionOrDocumentacion($user) || $this->isJefeImportaciones($user),
            'canAccessConfig' => $this->canManageColors($user) || $this->canManageActivities($user),
        ];

        $groupConfig = $roleGroup ? $roleGroup->configs->first() : null;

        // Orden de prioridad de colores (guardado como CSV en la BD)
        $defaultOrder = array('ACTIVIDAD', 'CONSOLIDADO', 'USUARIO', 'PRIORIDAD', 'COMPLETADO');
        $defaultMemberOrder = array('USUARIO', 'PRIORIDAD', 'ACTIVIDAD', 'CONSOLIDADO', 'COMPLETADO');

        $jefeOrder = $defaultOrder;
        $miembroOrder = $defaultMemberOrder;

        if ($groupConfig) {
            if (!empty($groupConfig->jefe_color_priority_order)) {
                $parsed = array_values(array_filter(array_map('trim', explode(',', $groupConfig->jefe_color_priority_order))));
                if (!empty($parsed)) {
                    $jefeOrder = $parsed;
                }
            }
            if (!empty($groupConfig->miembro_color_priority_order)) {
                $parsed = array_values(array_filter(array_map('trim', explode(',', $groupConfig->miembro_color_priority_order))));
                if (!empty($parsed)) {
                    $miembroOrder = $parsed;
                }
            }
        }

        return [
            'role_group' => $roleGroup ? [
                'id' => $roleGroup->id,
                'name' => $roleGroup->name,
                'code' => $roleGroup->code,
                'role_type' => $roleType,
            ] : null,
            'permissions' => $permissions,
            'colors' => $groupConfig ? [
                'prioridad' => $groupConfig->color_prioridad,
                'actividad' => $groupConfig->color_actividad,
                'consolidado' => $groupConfig->color_consolidado,
                'completado' => $groupConfig->color_completado,
            ] : null,
            'color_priority_order' => array(
                'jefe'    => $jefeOrder,
                'miembro' => $miembroOrder,
            ),
            'show_event_details' => $groupConfig ? (bool) $groupConfig->show_event_details : false,
            'usa_consolidado' => $roleGroup ? (bool) $roleGroup->usa_consolidado : true,
        ];
    }
}
