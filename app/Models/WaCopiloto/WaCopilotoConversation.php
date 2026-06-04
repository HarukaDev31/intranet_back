<?php

namespace App\Models\WaCopiloto;

use Illuminate\Database\Eloquent\Model;

class WaCopilotoConversation extends Model
{
    protected $table = 'wa_copiloto_conversations';

    protected $fillable = [
        'session_id',
        'contact_id',
        'wa_contact_id',
        'phone_e164',
        'contact_name',
        'contact_avatar_url',
        'channel_label',
        'assigned_user_id',
        'assigned_at',
        'status',
        'unread_count',
        'last_customer_message_at',
        'window_expires_at',
        'last_message_preview',
        'last_message_at',
        'last_message_id',
        'last_message_type',
        'last_message_delivery_status',
        'last_direction',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'last_customer_message_at' => 'datetime',
        'window_expires_at' => 'datetime',
        'last_message_at' => 'datetime',
        'unread_count' => 'integer',
    ];

    public function session()
    {
        return $this->belongsTo(WaCopilotoSession::class, 'session_id');
    }

    public function messages()
    {
        return $this->hasMany(WaCopilotoMessage::class, 'conversation_id');
    }
}
