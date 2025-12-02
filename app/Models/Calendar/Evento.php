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
        'title',
        'description',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'is_all_day',
        'is_for_me',
        'role_id',
        'role_name',
        'is_public',
        'created_by',
        'created_by_name',
        'color',
        'type',
        'parent_task_id'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'is_all_day' => 'boolean',
        'is_for_me' => 'boolean',
        'is_public' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relación con el usuario creador
     */
    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'created_by', 'ID_Usuario');
    }

    /**
     * Relación con el grupo/rol
     */
    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'role_id', 'ID_Grupo');
    }

    /**
     * Relación con la tarea padre (si es un día de tarea)
     */
    public function parentTask()
    {
        return $this->belongsTo(Evento::class, 'parent_task_id', 'id');
    }

    /**
     * Relación con días de tarea (si es una tarea)
     */
    public function taskDays()
    {
        return $this->hasMany(TaskDay::class, 'task_id', 'id');
    }

    /**
     * Scope para eventos visibles por un usuario
     */
    public function scopeVisibleForUser($query, $userId, $userRoleName = null)
    {
        return $query->where(function ($q) use ($userId, $userRoleName) {
            // Eventos públicos
            $q->where('is_public', true)
                // Eventos creados por el usuario
                ->orWhere('created_by', $userId)
                // Eventos para el usuario específico
                ->orWhere(function ($subQ) use ($userId) {
                    $subQ->where('is_for_me', true)
                        ->where('created_by', $userId);
                });
            
            // Si se proporciona el nombre del rol, incluir eventos para ese rol
            if ($userRoleName) {
                $q->orWhere(function ($subQ) use ($userRoleName) {
                    $subQ->whereNotNull('role_name')
                        ->where('role_name', $userRoleName)
                        ->where('is_for_me', false);
                });
            }
        });
    }

    /**
     * Scope para filtrar por rango de fechas
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate])
                ->orWhere(function ($subQ) use ($startDate, $endDate) {
                    $subQ->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                });
        });
    }
}

