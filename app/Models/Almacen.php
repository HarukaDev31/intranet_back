<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Almacen extends Model
{
    protected $table = 'almacen';
    protected $primaryKey = 'ID_Almacen';
    
    protected $fillable = [
        'ID_Organizacion',
        'No_Almacen',
        'Nu_Estado'
    ];

    /**
     * Relación con Organizacion
     */
    public function organizacion()
    {
        return $this->belongsTo(Organizacion::class, 'ID_Organizacion', 'ID_Organizacion');
    }
} 