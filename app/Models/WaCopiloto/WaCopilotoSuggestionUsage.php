<?php

namespace App\Models\WaCopiloto;

use Illuminate\Database\Eloquent\Model;

class WaCopilotoSuggestionUsage extends Model
{
    protected $table = 'wa_copiloto_suggestion_usages';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'insight_id',
        'user_id',
        'outcome',
        'suggested_text',
        'final_text',
    ];

    public function conversation()
    {
        return $this->belongsTo(WaCopilotoConversation::class, 'conversation_id');
    }
}
