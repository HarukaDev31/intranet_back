<?php

namespace App\Services\Calendar;

use App\Models\Calendar\CalendarActivity;
use Illuminate\Support\Collection;

class CalendarActivityService
{
    /**
     * Lista todas las actividades del catálogo (para dropdown "Actividad")
     */
    public function listActivities(): Collection
    {
        return CalendarActivity::orderBy('name')->get();
    }

    /**
     * Crear una nueva actividad en el catálogo
     */
    public function createActivity(string $name): CalendarActivity
    {
        return CalendarActivity::create(['name' => $name]);
    }

    /**
     * Actualizar nombre de una actividad del catálogo
     */
    public function updateActivity(int $id, string $name): ?CalendarActivity
    {
        $activity = CalendarActivity::find($id);
        if (!$activity) {
            return null;
        }
        $activity->update(['name' => $name]);
        return $activity->fresh();
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
