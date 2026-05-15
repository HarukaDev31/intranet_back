<?php

namespace App\Models\SoporteTi;

use Illuminate\Database\Eloquent\Model;

class SoporteTiEstado extends Model
{
    protected $table = 'soporte_ti_estados';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo_solicitud',
        'orden_kanban',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden_kanban' => 'integer',
    ];
}
