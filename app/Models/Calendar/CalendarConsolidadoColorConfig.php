<?php

namespace App\Models\Calendar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CargaConsolidada\Contenedor;

class CalendarConsolidadoColorConfig extends Model
{
    protected $table = 'calendar_consolidado_color_config';

    protected $fillable = [
        'calendar_id',
        'contenedor_id',
        'color_code',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class, 'calendar_id');
    }

    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'contenedor_id');
    }
}
