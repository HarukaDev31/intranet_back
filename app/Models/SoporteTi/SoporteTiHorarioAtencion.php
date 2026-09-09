<?php

namespace App\Models\SoporteTi;

use Illuminate\Database\Eloquent\Model;

class SoporteTiHorarioAtencion extends Model
{
    protected $table = 'soporte_ti_horario_atencion';

    protected $fillable = array(
        'dia_semana',
        'activo',
        'hora_inicio',
        'hora_fin',
        'timezone',
    );

    protected $casts = array(
        'dia_semana' => 'integer',
        'activo' => 'boolean',
    );
}
