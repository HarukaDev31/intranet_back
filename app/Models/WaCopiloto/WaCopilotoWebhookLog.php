<?php

namespace App\Models\WaCopiloto;

use Illuminate\Database\Eloquent\Model;

class WaCopilotoWebhookLog extends Model
{
    protected $table = 'wa_copiloto_webhook_logs';

    protected $fillable = [
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
