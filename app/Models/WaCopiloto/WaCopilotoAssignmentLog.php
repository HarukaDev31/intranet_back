<?php

namespace App\Models\WaCopiloto;

use Illuminate\Database\Eloquent\Model;

class WaCopilotoAssignmentLog extends Model
{
    public $timestamps = false;

    protected $table = 'wa_copiloto_assignment_logs';

    protected $fillable = [
        'conversation_id',
        'from_user_id',
        'to_user_id',
        'changed_by_user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
