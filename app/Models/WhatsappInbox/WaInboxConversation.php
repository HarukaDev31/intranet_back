<?php

namespace App\Models\WhatsappInbox;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $session_id
 * @property string|null $phone_e164
 * @property-read WaInboxSession|null $session
 */
class WaInboxConversation extends Model
{
    protected $table = 'wa_inbox_conversations';

    protected $fillable = [
        'session_id',
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

    public function session(): BelongsTo
    {
        return $this->belongsTo(WaInboxSession::class, 'session_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WaInboxMessage::class, 'conversation_id');
    }
}
