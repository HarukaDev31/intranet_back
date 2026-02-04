<?php

namespace App\Models\Calendar;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;
use App\Models\Grupo;
use App\Models\Calendar\TaskDay;

class Evento extends Model
{
    protected $table = 'calendar_events';
    protected $fillable = [
        'title', 'description', 'start_date', 'end_date', 'start_time', 'end_time',
        'is_all_day', 'is_for_me', 'role_id', 'role_name', 'is_public',
        'created_by', 'created_by_name', 'color', 'type', 'parent_task_id'
    ];
    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date',
        'start_time' => 'datetime:H:i:s', 'end_time' => 'datetime:H:i:s',
        'is_all_day' => 'boolean', 'is_for_me' => 'boolean', 'is_public' => 'boolean',
    ];
    public function creador() { return $this->belongsTo(Usuario::class, 'created_by', 'ID_Usuario'); }
    public function grupo() { return $this->belongsTo(Grupo::class, 'role_id', 'ID_Grupo'); }
    public function parentTask() { return $this->belongsTo(Evento::class, 'parent_task_id', 'id'); }
    public function taskDays() { return $this->hasMany(TaskDay::class, 'task_id', 'id'); }
    public function scopeVisibleForUser($query, $userId, $userRoleName = null) { /* ... */ }
    public function scopeInDateRange($query, $startDate, $endDate) { /* ... */ }
}
