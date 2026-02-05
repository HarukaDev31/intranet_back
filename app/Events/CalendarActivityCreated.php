<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalendarActivityCreated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $queue = 'notificaciones';

    /** @var int */
    public $calendarEventId;
    /** @var int|null */
    public $calendarId;
    /** @var int|null */
    public $contenedorId;
    /** @var array<int> */
    public $userIdsToNotify;
    /** @var int|null Usuario que realizó la acción (crear); el frontend no muestra popup a este usuario. */
    public $triggeredByUserId;

    /**
     * Create a new event instance.
     *
     * @param  array<int>  $userIdsToNotify  Jefe (solo si hay responsables) + responsables asignados.
     * @param  int|null  $triggeredByUserId  Usuario que creó la actividad (no verá el modal de recargar).
     */
    public function __construct(
        int $calendarEventId,
        ?int $calendarId = null,
        ?int $contenedorId = null,
        array $userIdsToNotify = [],
        ?int $triggeredByUserId = null
    ) {
        $this->calendarEventId = $calendarEventId;
        $this->calendarId = $calendarId;
        $this->contenedorId = $contenedorId;
        $this->userIdsToNotify = $userIdsToNotify;
        $this->triggeredByUserId = $triggeredByUserId;
    }

    /**
     * Get the channels the event should broadcast on.
     * Por usuario: jefe solo recibe de actividades con responsables; responsables solo del jefe.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];
        foreach (array_unique(array_filter($this->userIdsToNotify)) as $userId) {
            $channels[] = new PrivateChannel('App.Models.Usuario.' . $userId);
        }
        $channelNames = array_map(fn ($userId) => 'private-App.Models.Usuario.' . $userId, array_unique(array_filter($this->userIdsToNotify)));
        Log::info('CalendarActivityCreated broadcast', [
            'channels' => $channelNames,
            'event_id' => $this->calendarEventId,
            'broadcast_driver' => config('broadcasting.default'),
            'pusher_host' => config('broadcasting.connections.pusher.options.host'),
        ]);
        return $channels;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'calendar_event_id' => $this->calendarEventId,
            'calendar_id' => $this->calendarId,
            'contenedor_id' => $this->contenedorId,
            'message' => 'Nueva actividad de calendario creada',
            'triggered_by_user_id' => $this->triggeredByUserId,
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'CalendarActivityCreated';
    }
}
