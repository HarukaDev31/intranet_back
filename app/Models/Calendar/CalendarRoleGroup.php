<?php

namespace App\Models\Calendar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarRoleGroup extends Model
{
    protected $table = 'calendar_role_groups';

    protected $fillable = [
        'name',
        'code',
        'usa_consolidado',
        'is_active',
    ];

    protected $casts = [
        'usa_consolidado' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(CalendarRoleGroupMember::class, 'role_group_id');
    }

    public function configs(): HasMany
    {
        return $this->hasMany(CalendarRoleGroupConfig::class, 'role_group_id');
    }
}

