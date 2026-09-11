<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pais extends Model
{
    protected $table = 'pais';
    protected $primaryKey = 'ID_Pais';
    
    protected $fillable = [
        'No_Pais'
    ];

    public function flag()
    {
        return $this->hasOne(PaisFlag::class, 'id_pais', 'ID_Pais');
    }

    /**
     * Relación con Empresa
     */
    public function empresas()
    {
        return $this->hasMany(Empresa::class, 'ID_Pais', 'ID_Pais');
    }

    /**
     * Relación con User
     */
    public function users()
    {
        return $this->hasMany(User::class, 'ID_Pais', 'ID_Pais');
    }
} 