<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organizacion extends Model
{
    protected $table = 'organizacion';
    protected $primaryKey = 'ID_Organizacion';
    
    protected $fillable = [
        'Nu_Estado',
        'ID_Empresa'
    ];

    /**
     * Relación con Empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'ID_Empresa', 'ID_Empresa');
    }

    /**
     * Relación con Usuario
     */
    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'ID_Organizacion', 'ID_Organizacion');
    }

    /**
     * Relación con Almacen
     */
    public function almacenes()
    {
        return $this->hasMany(Almacen::class, 'ID_Organizacion', 'ID_Organizacion');
    }
} 