<?php

namespace App\Models\SoporteTi;

use Illuminate\Database\Eloquent\Model;

class SoporteTiEstadoTransicion extends Model
{
    protected $table = 'soporte_ti_estado_transiciones';

    public $timestamps = false;

    protected $fillable = [
        'estado_origen_id',
        'estado_destino_id',
        'rol',
        'tipo_solicitud',
    ];
}
