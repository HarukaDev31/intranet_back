<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrupoUsuario extends Model
{
    protected $table = 'grupo_usuario';
    protected $primaryKey = 'ID_Grupo_Usuario';
    public $timestamps = false;

    protected $fillable = [
        'ID_Usuario',
        'ID_Grupo',
        'ID_Empresa',
        'ID_Organizacion',
    ];

    /**
     * Relación con Usuario
     */
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'ID_Usuario', 'ID_Usuario');
    }

    /**
     * Relación con Grupo
     */
    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'ID_Grupo', 'ID_Grupo');
    }
} 