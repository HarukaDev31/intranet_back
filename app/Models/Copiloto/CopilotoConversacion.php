<?php

namespace App\Models\Copiloto;

use Illuminate\Database\Eloquent\Model;

class CopilotoConversacion extends Model
{
    protected $table = 'copiloto_conversaciones';

    protected $fillable = [
        'thread_key',
        'phone',
        'bitrix_chat_id',
        'linea',
        'contact_name',
        'last_message_at',
        'last_message_preview',
        'last_direction',
        'messages_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'messages_count' => 'integer',
    ];

    public function mensajes()
    {
        return $this->hasMany(WhatsappMessage::class, 'conversation_id');
    }
}
