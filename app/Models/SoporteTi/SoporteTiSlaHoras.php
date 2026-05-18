<?php

namespace App\Models\SoporteTi;

use Illuminate\Database\Eloquent\Model;

class SoporteTiSlaHoras extends Model
{
    protected $table = 'soporte_ti_sla_horas';

    protected $fillable = array(
        'tipo_solicitud',
        'ambito',
        'criticidad',
        'horas',
    );

    protected $casts = array(
        'horas' => 'integer',
    );
}
