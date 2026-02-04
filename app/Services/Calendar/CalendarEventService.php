<?php

namespace App\Services\Calendar;

use App\Models\Calendar\Calendar;
use App\Models\Calendar\CalendarEvent;
use App\Models\Calendar\CalendarEventCharge;
use App\Models\Calendar\CalendarEventChargeTracking;
use App\Models\Calendar\CalendarEventDay;
use App\Models\Calendar\CalendarActivity;
use App\Models\Calendar\CalendarUserColorConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Traits\FileTrait;
class CalendarEventService
{
    use FileTrait;
    /**
     * Eventos con filtros opcionales y visibilidad por rol (solo mis cargas si no es Jefe).
     */
    public function getEventsForUser(
        int $userId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $responsableId = null,
        ?int $contenedorId = null,
        ?string $status = null,
        ?int $priority = null,
        bool $onlyMyCharges = false
    ): Collection {
        $calendarIds = Calendar::pluck('id');
        $query = CalendarEvent::whereIn('calendar_id', $calendarIds)
            ->with(['activity', 'eventDays', 'charges.user', 'contenedor']);

        if ($onlyMyCharges) {
            $query->whereHas('charges', fn ($q) => $q->where('user_id', $userId));
        }
        if ($startDate && $endDate) {
            $query->whereHas('eventDays', fn ($q) => $q->whereBetween('date', [$startDate, $endDate]));
        }
        if ($responsableId !== null) {
            $query->whereHas('charges', fn ($q) => $q->where('user_id', $responsableId));
        }
        if ($contenedorId !== null) {
            $query->where('contenedor_id', $contenedorId);
        }
        if ($status !== null) {
            $query->whereHas('charges', fn ($q) => $q->where('status', $status));
        }
        if ($priority !== null) {
            $query->where('priority', $priority);
        }

        $events = $query->orderBy('created_at', 'desc')->get();
        return $events->map(fn ($event) => $this->formatEventForResponse($event));
    }

    /**
     * Un evento por ID si el usuario puede verlo (calendario propio o tiene carga).
     */
    public function getEventById(int $eventId, int $userId, bool $canSeeAllCalendars = false): ?array
    {
        $query = CalendarEvent::with(['activity', 'eventDays', 'charges.user', 'contenedor']);
        if (!$canSeeAllCalendars) {
            $query->whereHas('charges', fn ($q) => $q->where('user_id', $userId));
        }
        $event = $query->find($eventId);
        return $event ? $this->formatEventForResponse($event) : null;
    }

    /**
     * Formato según especificación API (days, charges con user + color, contenedor, duration).
     */
    public function formatEventForResponse(CalendarEvent $event): array
    {
        $days = $event->eventDays->sortBy('date');
        $first = $days->first();
        $last = $days->last();
        $startDate = $first ? $first->date->format('Y-m-d') : null;
        $endDate = $last ? $last->date->format('Y-m-d') : null;
        $duration = ($startDate && $endDate) ? (new \DateTime($endDate))->diff(new \DateTime($startDate))->days + 1 : 0;

        $daysArray = $event->eventDays->map(fn ($d) => [
            'id' => $d->id,
            'calendar_id' => $d->calendar_id,
            'calendar_event_id' => $d->calendar_event_id,
            'date' => $d->date->format('Y-m-d'),
        ])->values()->toArray();

        $colorByUserId = CalendarUserColorConfig::where('calendar_id', $event->calendar_id)
            ->get()
            ->keyBy('user_id');

        $chargesArray = $event->charges->map(function ($c) use ($colorByUserId) {
            $u = $c->user;
            $color = optional($colorByUserId->get($c->user_id))->color_code;
            return [
                'id' => $c->id,
                'calendar_id' => $c->calendar_id,
                'user_id' => $c->user_id,
                'calendar_event_id' => $c->calendar_event_id,
                'notes' => $c->notes,
                'assigned_at' => $c->assigned_at ? $c->assigned_at->format('c') : null,
                'removed_at' => $c->removed_at ? $c->removed_at->format('c') : null,
                'status' => $c->status,
                'user' => $u ? [
                    'id' => $u->ID_Usuario,
                    'nombre' => $u->No_Nombres_Apellidos ?: $u->No_Usuario,
                    'email' => $u->Txt_Email ?? null,
                    'avatar' => $this->generateImageUrl($u->Txt_Foto ?? null),
                    'color' => $color,
                ] : null,
            ];
        })->values()->toArray();

        $contenedor = null;
        if ($event->contenedor) {
            $c = $event->contenedor;
            $contenedor = [
                'id' => $c->id,
                'nombre' => 'Consolidado #' . $c->carga,
                'codigo' => 'CONT-' . ($c->f_inicio ? $c->f_inicio->format('Y') : date('Y')) . '-' . str_pad((string) $c->id, 3, '0', STR_PAD_LEFT),
            ];
        }

        return [
            'id' => $event->id,
            'calendar_id' => $event->calendar_id,
            'priority' => $event->priority,
            'name' => $event->name,
            'contenedor_id' => $event->contenedor_id,
            'notes' => $event->notes,
            'created_at' => $event->created_at ? $event->created_at->format('c') : null,
            'updated_at' => $event->updated_at ? $event->updated_at->format('c') : null,
            'deleted_at' => $event->deleted_at ? $event->deleted_at->format('c') : null,
            'days' => $daysArray,
            'charges' => $chargesArray,
            'contenedor' => $contenedor,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'duration' => $duration,
        ];
    }

    /**
     * Crear evento desde el formulario "Nueva actividad":
     * - activity_id (o name si se crea nueva),
     * - start_date, end_date,
     * - responsible_user_ids (array, máx. 2),
     * - contenedor_id (opcional)
     */
    public function createActivityEvent(int $calendarId, array $data): CalendarEvent
    {
        return DB::transaction(function () use ($calendarId, $data) {
            $calendar = Calendar::findOrFail($calendarId);
            $name = $this->resolveEventName($data);
            $startDate = $data['start_date'];
            $endDate = $data['end_date'];
            $responsibleIds = $data['responsible_user_ids'] ?? $data['responsable_ids'] ?? [];
            $contenedorId = $data['contenedor_id'] ?? null;
            $notes = $data['notes'] ?? null;

            // Limitar a 2 responsables
            $responsibleIds = array_slice(array_values($responsibleIds), 0, 2);

            $event = CalendarEvent::create([
                'calendar_id'   => $calendar->id,
                'activity_id'   => $data['activity_id'] ?? null,
                'priority'      => $data['priority'] ?? 0,
                'name'          => $name,
                'contenedor_id' => $contenedorId,
                'notes'         => $notes,
            ]);

            // Días del evento (desde start_date hasta end_date)
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
            $current = clone $start;
            while ($current <= $end) {
                CalendarEventDay::create([
                    'calendar_id'        => $calendar->id,
                    'calendar_event_id'  => $event->id,
                    'date'               => $current->format('Y-m-d'),
                ]);
                $current->modify('+1 day');
            }

            // Responsables (charges)
            $assignedAt = now();
            foreach ($responsibleIds as $userId) {
                CalendarEventCharge::create([
                    'calendar_id'        => $calendar->id,
                    'user_id'            => $userId,
                    'calendar_event_id'  => $event->id,
                    'assigned_at'        => $assignedAt,
                    'status'             => CalendarEventCharge::STATUS_PENDIENTE,
                ]);
            }

            $event->load(['activity', 'eventDays', 'charges.user', 'contenedor']);
            return $event;
        });
    }

    /**
     * Actualizar evento (nombre, notas, rango de fechas, responsables, contenedor).
     * Si canManageAll=true (Jefe), puede actualizar cualquier evento.
     */
    public function updateEvent(int $eventId, int $userId, array $data, bool $canManageAll = false): ?CalendarEvent
    {
        $calendarIds = $canManageAll ? Calendar::pluck('id') : Calendar::where('user_id', $userId)->pluck('id');
        $event = CalendarEvent::whereIn('calendar_id', $calendarIds)->find($eventId);
        if (!$event) {
            return null;
        }

        return DB::transaction(function () use ($event, $data) {
            $event->update(array_filter([
                'name' => $data['name'] ?? $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
                'activity_id' => $data['activity_id'] ?? null,
                'contenedor_id' => array_key_exists('contenedor_id', $data) ? $data['contenedor_id'] : null,
                'priority' => $data['priority'] ?? null,
            ], fn ($v) => $v !== null));

            if (!empty($data['start_date']) && !empty($data['end_date'])) {
                CalendarEventDay::where('calendar_event_id', $event->id)->delete();
                $start = new \DateTime($data['start_date']);
                $end = new \DateTime($data['end_date']);
                $current = clone $start;
                while ($current <= $end) {
                    CalendarEventDay::create([
                        'calendar_id' => $event->calendar_id,
                        'calendar_event_id' => $event->id,
                        'date' => $current->format('Y-m-d'),
                    ]);
                    $current->modify('+1 day');
                }
            }

            if (array_key_exists('responsible_user_ids', $data) || array_key_exists('responsable_ids', $data)) {
                CalendarEventCharge::where('calendar_event_id', $event->id)->delete();
                $ids = array_slice(array_values($data['responsible_user_ids'] ?? $data['responsable_ids'] ?? []), 0, 2);
                foreach ($ids as $uid) {
                    CalendarEventCharge::create([
                        'calendar_id' => $event->calendar_id,
                        'user_id' => $uid,
                        'calendar_event_id' => $event->id,
                        'assigned_at' => now(),
                        'status' => CalendarEventCharge::STATUS_PENDIENTE,
                    ]);
                }
            }

            $event->load(['activity', 'eventDays', 'charges.user', 'contenedor']);
            return $event;
        });
    }

    /**
     * Eliminar evento (soft delete). Si canManageAll=true (Jefe), puede eliminar cualquier evento.
     */
    public function deleteEvent(int $eventId, int $userId, bool $canManageAll = false): bool
    {
        $calendarIds = $canManageAll ? Calendar::pluck('id') : Calendar::where('user_id', $userId)->pluck('id');
        $event = CalendarEvent::whereIn('calendar_id', $calendarIds)->find($eventId);
        if (!$event) {
            return false;
        }
        return (bool) $event->delete();
    }

    public function updateChargeStatus(int $chargeId, int $userId, string $newStatus, ?int $changedBy = null): ?CalendarEventCharge
    {
        $charge = CalendarEventCharge::find($chargeId);
        if (!$charge) {
            return null;
        }
        $changedBy = $changedBy ?? $userId;
        $fromStatus = $charge->status;
        if ($fromStatus === $newStatus) {
            return $charge;
        }
        $charge->update(['status' => $newStatus]);
        CalendarEventChargeTracking::create([
            'calendar_event_charge_id' => $charge->id,
            'from_status' => $fromStatus,
            'to_status' => $newStatus,
            'changed_at' => now(),
            'changed_by' => $changedBy,
        ]);
        $charge->load('user');
        return $charge;
    }

    public function updateChargeNotes(int $chargeId, int $userId, string $notes): ?CalendarEventCharge
    {
        $charge = CalendarEventCharge::find($chargeId);
        if (!$charge) {
            return null;
        }
        $charge->update(['notes' => $notes]);
        return $charge;
    }

    public function updateEventPriority(int $eventId, int $userId, int $priority): ?CalendarEvent
    {
        $calendarIds = Calendar::pluck('id');
        $event = CalendarEvent::whereIn('calendar_id', $calendarIds)->find($eventId);
        if (!$event) {
            return null;
        }
        $event->update(['priority' => $priority]);
        $event->load(['activity', 'eventDays', 'charges.user', 'contenedor']);
        return $event;
    }

    public function updateEventNotes(int $eventId, int $userId, string $notes): ?CalendarEvent
    {
        $calendarIds = Calendar::pluck('id');
        $event = CalendarEvent::whereIn('calendar_id', $calendarIds)->find($eventId);
        if (!$event) {
            return null;
        }
        $event->update(['notes' => $notes]);
        $event->load(['activity', 'eventDays', 'charges.user', 'contenedor']);
        return $event;
    }

    public function addResponsable(int $eventId, int $userIdToAssign, int $requestUserId): ?CalendarEventCharge
    {
        $calendarIds = Calendar::pluck('id');
        $event = CalendarEvent::whereIn('calendar_id', $calendarIds)->find($eventId);
        if (!$event) {
            return null;
        }
        $count = CalendarEventCharge::where('calendar_event_id', $eventId)->count();
        if ($count >= 2) {
            return null;
        }
        $exists = CalendarEventCharge::where('calendar_event_id', $eventId)->where('user_id', $userIdToAssign)->exists();
        if ($exists) {
            return null;
        }
        $charge = CalendarEventCharge::create([
            'calendar_id' => $event->calendar_id,
            'user_id' => $userIdToAssign,
            'calendar_event_id' => $event->id,
            'assigned_at' => now(),
            'status' => CalendarEventCharge::STATUS_PENDIENTE,
        ]);
        CalendarEventChargeTracking::create([
            'calendar_event_charge_id' => $charge->id,
            'from_status' => null,
            'to_status' => CalendarEventCharge::STATUS_PENDIENTE,
            'changed_at' => $charge->assigned_at,
            'changed_by' => $requestUserId,
        ]);
        $charge->load('user');
        return $charge;
    }

    public function removeResponsable(int $eventId, int $userIdToRemove, int $requestUserId): bool
    {
        $calendarIds = Calendar::pluck('id');
        $event = CalendarEvent::whereIn('calendar_id', $calendarIds)->find($eventId);
        if (!$event) {
            return false;
        }
        $charge = CalendarEventCharge::where('calendar_event_id', $eventId)->where('user_id', $userIdToRemove)->first();
        if (!$charge) {
            return false;
        }
        $charge->update(['removed_at' => now()]);
        return (bool) $charge->delete();
    }

    /**
     * Progreso del equipo (por eventos) y por responsable (por charges).
     */
    public function getProgress(?string $startDate = null, ?string $endDate = null, ?int $calendarId = null): array
    {
        $eventQuery = CalendarEvent::with(['charges' => fn ($q) => $q->whereNull('removed_at')]);
        if ($calendarId) {
            $eventQuery->where('calendar_id', $calendarId);
        }
        if ($startDate && $endDate) {
            $eventQuery->whereHas('eventDays', fn ($q) => $q->whereBetween('date', [$startDate, $endDate]));
        }
        $events = $eventQuery->get();
        $teamCompletadas = 0;
        $teamEnProgreso = 0;
        $teamPendientes = 0;
        $byUser = [];
        foreach ($events as $event) {
            $charges = $event->charges;
            if ($charges->isEmpty()) {
                $teamPendientes++;
                continue;
            }
            $allCompleted = $charges->every(fn ($c) => $c->status === CalendarEventCharge::STATUS_COMPLETADO);
            $anyProgress = $charges->contains(fn ($c) => $c->status === CalendarEventCharge::STATUS_PROGRESO);
            if ($allCompleted) {
                $teamCompletadas++;
            } elseif ($anyProgress || $charges->contains(fn ($c) => $c->status === CalendarEventCharge::STATUS_COMPLETADO)) {
                $teamEnProgreso++;
            } else {
                $teamPendientes++;
            }
            foreach ($charges as $c) {
                $uid = $c->user_id;
                if (!isset($byUser[$uid])) {
                    $u = $c->user;
                    $byUser[$uid] = [
                        'user_id' => $uid,
                        'nombre' => $u ? ($u->No_Nombres_Apellidos ?: $u->No_Usuario) : null,
                        'color' => null,
                        'total_asignadas' => 0,
                        'completadas' => 0,
                        'en_progreso' => 0,
                        'pendientes' => 0,
                    ];
                }
                $byUser[$uid]['total_asignadas']++;
                if ($c->status === CalendarEventCharge::STATUS_COMPLETADO) {
                    $byUser[$uid]['completadas']++;
                } elseif ($c->status === CalendarEventCharge::STATUS_PROGRESO) {
                    $byUser[$uid]['en_progreso']++;
                } else {
                    $byUser[$uid]['pendientes']++;
                }
            }
        }
        $totalActividades = $events->count();
        $colorMap = [];
        if ($calendarId) {
            $colorMap = CalendarUserColorConfig::where('calendar_id', $calendarId)
                ->pluck('color_code', 'user_id')
                ->toArray();
        } else {
            $colorMap = CalendarUserColorConfig::orderBy('calendar_id')
                ->pluck('color_code', 'user_id')
                ->toArray();
        }
        foreach ($byUser as &$row) {
            $row['porcentaje_completado'] = $row['total_asignadas'] > 0
                ? (int) round(($row['completadas'] / $row['total_asignadas']) * 100)
                : 0;
            $row['color'] = $colorMap[$row['user_id']] ?? null;
        }
        return [
            'team' => [
                'total_actividades' => $totalActividades,
                'completadas' => $teamCompletadas,
                'en_progreso' => $teamEnProgreso,
                'pendientes' => $teamPendientes,
                'porcentaje_completado' => $totalActividades > 0 ? (int) round(($teamCompletadas / $totalActividades) * 100) : 0,
            ],
            'by_responsable' => array_values($byUser),
        ];
    }

    /**
     * Formatea un registro de tracking para la respuesta API.
     */
    private function formatTrackingRow(CalendarEventChargeTracking $row): array
    {
        $charge = $row->calendarEventCharge;
        $u = $charge ? $charge->user : null;
        $changedBy = $row->changedByUser;
        return [
            'id' => $row->id,
            'calendar_event_charge_id' => $row->calendar_event_charge_id,
            'from_status' => $row->from_status,
            'to_status' => $row->to_status,
            'changed_at' => $row->changed_at ? $row->changed_at->format('c') : null,
            'changed_by' => $row->changed_by,
            'changed_by_user' => $changedBy ? [
                'id' => $changedBy->ID_Usuario,
                'nombre' => $changedBy->No_Nombres_Apellidos ?: $changedBy->No_Usuario,
                'email' => $changedBy->Txt_Email ?? null,
            ] : null,
            'charge' => $charge ? [
                'id' => $charge->id,
                'user_id' => $charge->user_id,
                'calendar_event_id' => $charge->calendar_event_id,
                'status' => $charge->status,
                'user' => $u ? [
                    'id' => $u->ID_Usuario,
                    'nombre' => $u->No_Nombres_Apellidos ?: $u->No_Usuario,
                    'email' => $u->Txt_Email ?? null,
                ] : null,
            ] : null,
        ];
    }

    /**
     * Historial de cambios de estado de un charge. Si !canSeeAll, solo si el charge es del usuario.
     */
    public function getTrackingForCharge(int $chargeId, int $userId, bool $canSeeAll): ?array
    {
        $charge = CalendarEventCharge::with(['user'])->find($chargeId);
        if (!$charge) {
            return null;
        }
        if (!$canSeeAll && $charge->user_id !== $userId) {
            return null;
        }
        $rows = CalendarEventChargeTracking::where('calendar_event_charge_id', $chargeId)
            ->with(['changedByUser', 'calendarEventCharge.user'])
            ->orderBy('changed_at')
            ->get();
        return $rows->map(fn ($row) => $this->formatTrackingRow($row))->values()->all();
    }

    /**
     * Historial de cambios de estado de todos los charges de una actividad (calendar_event).
     * Si !canSeeAll, solo si el usuario está asignado a la actividad.
     */
    public function getTrackingForActivity(int $activityId, int $userId, bool $canSeeAll): ?array
    {
        $event = CalendarEvent::find($activityId);
        if (!$event) {
            return null;
        }
        if (!$canSeeAll) {
            $isAssigned = CalendarEventCharge::where('calendar_event_id', $activityId)->where('user_id', $userId)->exists();
            if (!$isAssigned) {
                return null;
            }
        }
        $chargeIds = CalendarEventCharge::where('calendar_event_id', $activityId)->pluck('id');
        if ($chargeIds->isEmpty()) {
            return [];
        }
        $rows = CalendarEventChargeTracking::whereIn('calendar_event_charge_id', $chargeIds->toArray())
            ->with(['changedByUser', 'calendarEventCharge.user'])
            ->orderBy('changed_at')
            ->get();
        return $rows->map(fn ($row) => $this->formatTrackingRow($row))->values()->all();
    }

    private function resolveEventName(array $data): string
    {
        if (!empty($data['name'])) {
            return $data['name'];
        }
        if (!empty($data['title'])) {
            return $data['title'];
        }
        if (!empty($data['activity_id'])) {
            $activity = CalendarActivity::find($data['activity_id']);
            return $activity ? $activity->name : 'Actividad';
        }
        return 'Actividad';
    }
}
