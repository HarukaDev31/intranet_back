<?php

namespace App\Models\WaCopiloto;

use Illuminate\Database\Eloquent\Model;

class WaCopilotoSession extends Model
{
    protected $table = 'wa_copiloto_sessions';

    protected $fillable = [
        'slug',
        'phone_number_id',
        'waba_id',
        'display_number',
        'label',
        'template_name_prefix',
        'is_active',
        'last_webhook_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_webhook_at' => 'datetime',
    ];

    public function conversations()
    {
        return $this->hasMany(WaCopilotoConversation::class, 'session_id');
    }
}
