<?php

namespace App\Services\Calendar;

use App\Models\Calendar\CalendarActivity;
use App\Models\Calendar\CalendarEvent;
use Illuminate\Support\Collection;

class CalendarActivityService
{
    /**
     * Lista todas las actividades del catálogo ordenadas por `orden` y luego por `name`
     */
    public function listActivities(): Collection
    {
        return CalendarActivity::orderBy('orden')->orderBy('name')->get();
    }

    /**
     * Crear una nueva actividad en el catálogo asignando el siguiente orden
     */
    public function createActivity(string $name): CalendarActivity
    {
        $maxOrden = CalendarActivity::max('orden') ?? 0;
        return CalendarActivity::create(['name' => $name, 'orden' => $maxOrden + 1]);
    }

    /**
     * Actualizar nombre y/o color de una actividad del catálogo.
     * Además, asigna este activity_id a todos los eventos que tengan el mismo nombre y activity_id null,
     * para que hereden el color sin tener que editar cada evento.
     */
    public function updateActivity(int $id, string $name, ?string $colorCode = null): ?CalendarActivity
    {
        $activity = CalendarActivity::find($id);
        if (!$activity) {
            return null;
        }
        $data = ['name' => $name];
        if ($colorCode !== null) {
            $data['color_code'] = $colorCode ?: null;
        }
        $activity->update($data);

        // Asignar esta actividad a eventos que tienen el mismo nombre pero sin activity_id
        CalendarEvent::whereNull('activity_id')
            ->where('name', $name)
            ->update(['activity_id' => $id]);

        return $activity->fresh();
    }

    /**
     * Reordenar actividades del catálogo dado un array de ids en el nuevo orden
     */
    public function reorderActivities(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            CalendarActivity::where('id', $id)->update(['orden' => $index + 1]);
        }
    }

    /**
     * Eliminar una actividad del catálogo. Falla si está en uso en algún evento.
     */
    public function deleteActivity(int $id): bool
    {
        $activity = CalendarActivity::find($id);
        if (!$activity) {
            return false;
        }
        if ($activity->calendarEvents()->exists()) {
            return false; // indicar que está en uso
        }
        return $activity->delete();
    }
}
