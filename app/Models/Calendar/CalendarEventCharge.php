<?php

namespace App\Models\Calendar;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarEventCharge extends Model
{
    const STATUS_PENDIENTE = 'PENDIENTE';
    const STATUS_PROGRESO = 'PROGRESO';
    const STATUS_COMPLETADO = 'COMPLETADO';

    protected $table = 'calendar_event_charges';

    protected $fillable = [
        'calendar_id',
        'user_id',
        'calendar_event_id',
        'notes',
        'assigned_at',
        'removed_at',
        'status',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'removed_at' => 'datetime',
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
     * Usuario asignado
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'user_id', 'ID_Usuario');
    }

    /**
     * Evento del calendario
     */
    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    /**
     * Historial de cambios de estado
     */
    public function tracking(): HasMany
    {
        return $this->hasMany(CalendarEventChargeTracking::class, 'calendar_event_charge_id');
    }
}
