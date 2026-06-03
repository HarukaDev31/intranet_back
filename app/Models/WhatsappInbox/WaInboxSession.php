<?php

namespace App\Models\WhatsappInbox;

use Illuminate\Database\Eloquent\Model;

class WaInboxSession extends Model
{
    protected $table = 'wa_inbox_sessions';

    protected $fillable = [
        'phone_number_id',
        'display_number',
        'label',
        'is_active',
        'last_webhook_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_webhook_at' => 'datetime',
    ];

    public function conversations()
    {
        return $this->hasMany(WaInboxConversation::class, 'session_id');
    }
}
