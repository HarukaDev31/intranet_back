<?php

namespace App\Models\ManualUsuario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualPagina extends Model
{
    protected $table = 'manual_paginas';

    protected $fillable = [
        'role_slug',
        'id_grupo',
        'modulo_key',
        'titulo',
        'descripcion',
        'orden',
        'publicado',
    ];

    protected $casts = [
        'publicado' => 'boolean',
        'orden' => 'integer',
        'id_grupo' => 'integer',
    ];

    public function bloques(): HasMany
    {
        return $this->hasMany(ManualBloque::class, 'pagina_id')->orderBy('orden');
    }
}
