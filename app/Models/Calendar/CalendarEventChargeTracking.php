<?php

namespace App\Models\Calendar;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventChargeTracking extends Model
{
    public $timestamps = false;

    protected $table = 'calendar_event_charge_tracking';

    protected $fillable = [
        'calendar_event_charge_id',
        'from_status',
        'to_status',
        'changed_at',
        'changed_by',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    /**
     * Carga (asignación) que se está trackeando
     */
    public function calendarEventCharge(): BelongsTo
    {
        return $this->belongsTo(CalendarEventCharge::class, 'calendar_event_charge_id');
    }

    /**
     * Usuario que realizó el cambio de estado
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'changed_by', 'ID_Usuario');
    }
}
