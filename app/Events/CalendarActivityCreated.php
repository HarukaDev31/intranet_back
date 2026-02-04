<?php

namespace App\Events;

use App\Models\Calendar\CalendarEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CalendarActivityCreated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $queue = 'notificaciones';

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $calendarEventId,
        public ?int $calendarId = null,
        public ?int $contenedorId = null
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('Documentacion-notifications'),
            new PrivateChannel('Coordinacion-notifications'),
            new PrivateChannel('JefeImportacion-notifications'),
        ];
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
