<?php

namespace App\Models\Calendar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventDay extends Model
{
    protected $table = 'calendar_event_days';

    protected $fillable = [
        'calendar_id',
        'calendar_event_id',
        'date',
    ];

    protected $casts = [
        'calendar_id' => 'integer',
        'calendar_event_id' => 'integer',
        'date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Calendario al que pertenece
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class, 'calendar_id');
    }

    /**
     * Evento del calendario
     */
    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }
}
