<?php

namespace App\Models\WaCopiloto;

use Illuminate\Database\Eloquent\Model;

class WaCopilotoMessage extends Model
{
    protected $table = 'wa_copiloto_messages';

    protected $fillable = [
        'conversation_id',
        'session_id',
        'direction',
        'body',
        'message_type',
        'template_name',
        'template_params',
        'media_url',
        'media_mime',
        'meta_message_id',
        'delivery_status',
        'failed_reason',
        'sent_at',
        'sent_by_user_id',
    ];

    protected $casts = [
        'template_params' => 'array',
        'sent_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(WaCopilotoConversation::class, 'conversation_id');
    }

    public function session()
    {
        return $this->belongsTo(WaCopilotoSession::class, 'session_id');
    }
}
