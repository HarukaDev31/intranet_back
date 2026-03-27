<?php

namespace App\Services\Calendar;

use App\Models\Calendar\CalendarRoleGroupMember;
use Illuminate\Support\Facades\Cache;

class CalendarResponsablesCacheService
{
    public static function cacheKey(int $userId, int $groupKey): string
    {
        return 'calendar:responsables:user:' . $userId . ':group:' . $groupKey;
    }

    /**
     * Invalida la caché de GET /calendar/responsables para todos los miembros actuales del grupo.
     * Opcional: limpia el fallback sin grupo (group:0) para un usuario que acaba de unirse al grupo.
     */
    public static function forgetForRoleGroup(int $roleGroupId, ?int $forgetGroupZeroForUserId = null): void
    {
        $userIds = CalendarRoleGroupMember::where('role_group_id', $roleGroupId)
            ->pluck('user_id')
            ->unique()
            ->values();

        foreach ($userIds as $uid) {
            Cache::forget(self::cacheKey((int) $uid, $roleGroupId));
        }

        if ($forgetGroupZeroForUserId !== null) {
            Cache::forget(self::cacheKey($forgetGroupZeroForUserId, 0));
        }
    }

    /**
     * Tras quitar un miembro: invalida la caché con ese groupKey para todos los que estaban
     * en el grupo antes del delete (incluido el eliminado) y el fallback group:0 del eliminado.
     *
     * @param  array<int>  $userIdsInGroupBeforeDelete
     */
    public static function forgetAfterMemberRemoved(int $roleGroupId, int $removedUserId, array $userIdsInGroupBeforeDelete): void
    {
        foreach ($userIdsInGroupBeforeDelete as $uid) {
            Cache::forget(self::cacheKey((int) $uid, $roleGroupId));
        }

        Cache::forget(self::cacheKey($removedUserId, 0));
    }
}
