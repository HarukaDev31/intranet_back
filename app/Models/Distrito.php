<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Distrito extends Model
{
    protected $table = 'distrito';
    protected $primaryKey = 'ID_Distrito';
    
    protected $fillable = [
        'No_Distrito',
        'ID_Provincia'
    ];

    /**
     * Relación con Provincia
     */
    public function provincia()
    {
        return $this->belongsTo(Provincia::class, 'ID_Provincia', 'ID_Provincia');
    }
} 