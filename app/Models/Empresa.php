<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $table = 'empresa';
    protected $primaryKey = 'ID_Empresa';
    
    protected $fillable = [
        'Nu_Estado',
        'ID_Pais'
    ];

    /**
     * Relación con Usuario
     */
    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'ID_Empresa', 'ID_Empresa');
    }

    /**
     * Relación con Organizacion
     */
    public function organizaciones()
    {
        return $this->hasMany(Organizacion::class, 'ID_Empresa', 'ID_Empresa');
    }

    /**
     * Relación con Pais
     */
    public function pais()
    {
        return $this->belongsTo(Pais::class, 'ID_Pais', 'ID_Pais');
    }

    /**
     * Relación con Moneda
     */
    public function moneda()
    {
        return $this->hasOne(Moneda::class, 'ID_Empresa', 'ID_Empresa');
    }

    /**
     * Relación con SubdominioTiendaVirtual
     */
    public function subdominioTiendaVirtual()
    {
        return $this->hasOne(SubdominioTiendaVirtual::class, 'ID_Empresa', 'ID_Empresa');
    }
} 