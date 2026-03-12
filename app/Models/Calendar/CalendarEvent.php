<?php

namespace App\Models\Calendar;

use App\Models\CargaConsolidada\Contenedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use SoftDeletes;

    protected $table = 'calendar_events';

    protected $fillable = [
        'calendar_id',
        'role_group_id',
        'activity_id',
        'priority',
        'name',
        'contenedor_id',
        'notes',
        'display_order',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'priority' => 'integer',
    ];

    /**
     * Calendario al que pertenece el evento
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class, 'calendar_id');
    }

    /**
     * Grupo de rol al que pertenece el evento (para segmentar por grupo de calendario).
     */
    public function roleGroup(): BelongsTo
    {
        return $this->belongsTo(CalendarRoleGroup::class, 'role_group_id');
    }

    /**
     * Actividad del catálogo (opcional)
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(CalendarActivity::class, 'activity_id');
    }

    /**
     * Contenedor asociado (opcional)
     */
    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'contenedor_id');
    }

    /**
     * Días en los que ocurre el evento
     */
    public function eventDays(): HasMany
    {
        return $this->hasMany(CalendarEventDay::class, 'calendar_event_id');
    }

    /**
     * Asignaciones (usuarios responsables) del evento
     */
    public function charges(): HasMany
    {
        return $this->hasMany(CalendarEventCharge::class, 'calendar_event_id');
    }
}
