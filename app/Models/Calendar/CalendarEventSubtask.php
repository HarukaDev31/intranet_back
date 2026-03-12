<?php

namespace App\Models\Calendar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventSubtask extends Model
{
    const STATUS_PENDIENTE = 'PENDIENTE';
    const STATUS_PROGRESO = 'PROGRESO';
    const STATUS_COMPLETADO = 'COMPLETADO';

    protected $table = 'calendar_event_subtasks';

    protected $fillable = [
        'calendar_event_charge_id',
        'name',
        'duration_hours',
        'end_date',
        'status',
    ];

    protected $casts = [
        'end_date' => 'date',
    ];

    /**
     * Charge/responsable al que pertenece la subtarea.
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(CalendarEventCharge::class, 'calendar_event_charge_id');
    }
}

