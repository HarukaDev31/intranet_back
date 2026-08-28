<?php

namespace App\Models\SoporteTi;

use App\Models\Grupo;
use Illuminate\Database\Eloquent\Model;

class SoporteTiArea extends Model
{
    protected $table = 'soporte_ti_areas';

    protected $fillable = array(
        'nombre',
        'orden',
        'activo',
    );

    protected $casts = array(
        'orden' => 'integer',
        'activo' => 'boolean',
    );

    public function grupos()
    {
        return $this->belongsToMany(
            Grupo::class,
            'soporte_ti_area_grupo',
            'area_id',
            'grupo_id',
            'id',
            'ID_Grupo'
        )->withTimestamps();
    }
}
