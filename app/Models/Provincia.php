<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provincia extends Model
{
    protected $table = 'provincia';
    protected $primaryKey = 'ID_Provincia';
    
    protected $fillable = [
        'No_Provincia',
        'ID_Departamento'
    ];

    /**
     * Relación con Departamento
     */
    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'ID_Departamento', 'ID_Departamento');
    }

    /**
     * Relación con Distritos
     */
    public function distritos()
    {
        return $this->hasMany(Distrito::class, 'ID_Provincia', 'ID_Provincia');
    }
} 