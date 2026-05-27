<?php

namespace App\Models\Copiloto;

use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'conversation_id',
        'phone',
        'bitrix_chat_id',
        'bitrix_msg_id',
        'direction',
        'body',
        'source',
        'linea',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function conversacion()
    {
        return $this->belongsTo(CopilotoConversacion::class, 'conversation_id');
    }
}

