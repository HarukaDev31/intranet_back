<?php

namespace App\Models\Calendar;

use App\Models\Usuario;
use App\Models\CargaConsolidada\Contenedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Calendar extends Model
{
    protected $table = 'calendars';

    protected $fillable = [
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Usuario propietario del calendario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'user_id', 'ID_Usuario');
    }

    /**
     * Eventos del calendario
     */
    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'calendar_id');
    }

    /**
     * Días de eventos del calendario
     */
    public function eventDays(): HasMany
    {
        return $this->hasMany(CalendarEventDay::class, 'calendar_id');
    }

    /**
     * Asignaciones (charges) del calendario
     */
    public function charges(): HasMany
    {
        return $this->hasMany(CalendarEventCharge::class, 'calendar_id');
    }
}
