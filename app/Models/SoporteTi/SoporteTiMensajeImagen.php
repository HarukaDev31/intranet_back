<?php

namespace App\Models\SoporteTi;

use Illuminate\Database\Eloquent\Model;

class SoporteTiMensajeImagen extends Model
{
    protected $table = 'soporte_ti_mensaje_imagenes';

    public $timestamps = false;

    protected $fillable = [
        'mensaje_id',
        'url',
        'nombre',
        'tamano',
        'orden',
    ];
}
