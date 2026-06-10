<?php

namespace App\Models\WaCopiloto;

use Illuminate\Database\Eloquent\Model;

class WaCopilotoScheduledMessage extends Model
{
    protected $table = 'wa_copiloto_scheduled_messages';

    protected $fillable = [
        'conversation_id',
        'session_id',
        'created_by_user_id',
        'body',
        'message_type',
        'template_params',
        'scheduled_at',
        'status',
        'failed_reason',
        'message_id',
    ];

    protected $casts = [
        'template_params' => 'array',
        'scheduled_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(WaCopilotoConversation::class, 'conversation_id');
    }
}
