<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $table = 'grupo';
    protected $primaryKey = 'ID_Grupo';
    
    protected $fillable = [
        'ID_Empresa',
        'ID_Organizacion',
        'No_Grupo',
        'No_Grupo_Descripcion',
        'Nu_Tipo_Privilegio_Acceso',
        'Nu_Notificacion'
    ];

    /**
     * Relación con Empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'ID_Empresa', 'ID_Empresa');
    }

    /**
     * Relación con Organizacion
     */
    public function organizacion()
    {
        return $this->belongsTo(Organizacion::class, 'ID_Organizacion', 'ID_Organizacion');
    }

    /**
     * Relación con GrupoUsuario
     */
    public function gruposUsuario()
    {
        return $this->hasMany(GrupoUsuario::class, 'ID_Grupo', 'ID_Grupo');
    }
} 