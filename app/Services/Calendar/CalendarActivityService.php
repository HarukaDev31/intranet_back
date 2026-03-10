<?php

namespace App\Services\Calendar;

use App\Models\Calendar\CalendarActivity;
use App\Models\Calendar\CalendarEvent;
use Illuminate\Support\Collection;

class CalendarActivityService
{
    /**
     * Lista las actividades del catálogo del grupo indicado, ordenadas por `orden` y luego por `name`
     */
    public function listActivities(int $roleGroupId): Collection
    {
        return CalendarActivity::where('role_group_id', $roleGroupId)
            ->orderBy('orden')
            ->orderBy('name')
            ->get();
    }

    /**
     * Crear una nueva actividad en el catálogo del grupo asignando el siguiente orden
     */
    public function createActivity(string $name, int $roleGroupId): CalendarActivity
    {
        $maxOrden = CalendarActivity::where('role_group_id', $roleGroupId)->max('orden') ?? 0;
        return CalendarActivity::create([
            'name'           => $name,
            'orden'          => $maxOrden + 1,
            'role_group_id'  => $roleGroupId,
            'allow_saturday' => true,
            'allow_sunday'   => true,
        ]);
    }

    /**
     * Actualizar nombre y/o color de una actividad del catálogo del grupo.
     * Además, asigna este activity_id a todos los eventos que tengan el mismo nombre y activity_id null,
     * para que hereden el color sin tener que editar cada evento.
     */
    public function updateActivity(int $id, string $name, ?string $colorCode = null, array $extras = [], ?int $roleGroupId = null): ?CalendarActivity
    {
        $query = CalendarActivity::where('id', $id);
        if ($roleGroupId !== null) {
            $query->where('role_group_id', $roleGroupId);
        }
        $activity = $query->first();
        if (!$activity) {
            return null;
        }
        $data = ['name' => $name];
        if ($colorCode !== null) {
            $data['color_code'] = $colorCode ?: null;
        }
        if (array_key_exists('allow_saturday', $extras)) {
            $data['allow_saturday'] = (bool) $extras['allow_saturday'];
        }
        if (array_key_exists('allow_sunday', $extras)) {
            $data['allow_sunday'] = (bool) $extras['allow_sunday'];
        }
        if (array_key_exists('default_priority', $extras)) {
            $data['default_priority'] = (int) ($extras['default_priority'] ?? 0);
        }
        $activity->update($data);

        // Asignar esta actividad a eventos que tienen el mismo nombre pero sin activity_id
        CalendarEvent::whereNull('activity_id')
            ->where('name', $name)
            ->update(['activity_id' => $id]);

        return $activity->fresh();
    }

    /**
     * Reordenar actividades del catálogo del grupo dado un array de ids en el nuevo orden
     */
    public function reorderActivities(array $orderedIds, int $roleGroupId): void
    {
        foreach ($orderedIds as $index => $id) {
            CalendarActivity::where('id', $id)->where('role_group_id', $roleGroupId)->update(['orden' => $index + 1]);
        }
    }

    /**
     * Eliminar una actividad del catálogo del grupo. Falla si está en uso en algún evento.
     */
    public function deleteActivity(int $id, ?int $roleGroupId = null): bool
    {
        $query = CalendarActivity::where('id', $id);
        if ($roleGroupId !== null) {
            $query->where('role_group_id', $roleGroupId);
        }
        $activity = $query->first();
        if (!$activity) {
            return false;
        }
        if ($activity->calendarEvents()->exists()) {
            return false; // indicar que está en uso
        }
        return $activity->delete();
    }
}
