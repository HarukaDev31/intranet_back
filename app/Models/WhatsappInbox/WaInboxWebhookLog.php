<?php

namespace App\Models\WhatsappInbox;

use Illuminate\Database\Eloquent\Model;

class WaInboxWebhookLog extends Model
{
    protected $table = 'wa_inbox_webhook_logs';

    protected $fillable = [
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
