<?php

namespace App\Models\Calendar;

use Illuminate\Database\Eloquent\Model;
use App\Models\Calendar\Evento;

class TaskDay extends Model
{
    protected $table = 'calendar_task_days';
    protected $fillable = [
        'task_id',
        'day_date',
        'start_time',
        'end_time',
        'is_all_day',
        'color'
    ];

    protected $casts = [
        'day_date' => 'date',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'is_all_day' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relación con la tarea padre
     */
    public function task()
    {
        return $this->belongsTo(Evento::class, 'task_id', 'id');
    }
}

