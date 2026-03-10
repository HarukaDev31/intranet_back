<?php

namespace App\Models\Calendar;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarRoleGroupMember extends Model
{
    protected $table = 'calendar_role_group_members';

    protected $fillable = [
        'role_group_id',
        'user_id',
        'role_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(CalendarRoleGroup::class, 'role_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'user_id', 'ID_Usuario');
    }
}

