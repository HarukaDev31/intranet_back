<?php

namespace App\Models\WhatsappInbox;

use App\Models\Concerns\TruncatesFailedReason;
use Illuminate\Database\Eloquent\Model;

class WaInboxMessage extends Model
{
    use TruncatesFailedReason;
    protected $table = 'wa_inbox_messages';

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
        return $this->belongsTo(WaInboxConversation::class, 'conversation_id');
    }
}
