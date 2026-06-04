<?php

namespace App\Models\WaCopiloto;

use Illuminate\Database\Eloquent\Model;

class WaCopilotoMessageInsight extends Model
{
    protected $table = 'wa_copiloto_message_insights';

    protected $fillable = [
        'message_id',
        'conversation_id',
        'phone_e164',
        'kind',
        'label',
        'body',
        'score',
        'sort_order',
    ];

    public function message()
    {
        return $this->belongsTo(WaCopilotoMessage::class, 'message_id');
    }
}
