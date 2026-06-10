<?php

namespace App\Models\WaCopiloto;

use Illuminate\Database\Eloquent\Model;

class WaCopilotoPipelineTransition extends Model
{
    public $timestamps = false;

    protected $table = 'wa_copiloto_pipeline_transitions';

    protected $fillable = [
        'conversation_id',
        'from_stage_id',
        'to_stage_id',
        'major_from',
        'major_to',
        'changed_by_user_id',
        'note',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function fromStage()
    {
        return $this->belongsTo(WaCopilotoPipelineStage::class, 'from_stage_id');
    }

    public function toStage()
    {
        return $this->belongsTo(WaCopilotoPipelineStage::class, 'to_stage_id');
    }
}
