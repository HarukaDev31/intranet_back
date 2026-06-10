<?php

namespace App\Models\WaCopiloto;

use Illuminate\Database\Eloquent\Model;

class WaCopilotoPipelineStage extends Model
{
    protected $table = 'wa_copiloto_pipeline_stages';

    protected $fillable = [
        'major',
        'slug',
        'label',
        'sort_order',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];
}
