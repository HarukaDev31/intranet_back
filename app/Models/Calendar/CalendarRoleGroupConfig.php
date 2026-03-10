<?php

namespace App\Models\Calendar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarRoleGroupConfig extends Model
{
    protected $table = 'calendar_role_group_configs';

    protected $fillable = [
        'role_group_id',
        'color_prioridad',
        'color_actividad',
        'color_consolidado',
        'color_completado',
        'jefe_color_priority_order',
        'miembro_color_priority_order',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(CalendarRoleGroup::class, 'role_group_id');
    }
}

