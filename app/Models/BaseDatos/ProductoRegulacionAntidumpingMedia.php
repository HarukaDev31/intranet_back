<?php

namespace App\Models\BaseDatos;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoRegulacionAntidumpingMedia extends BaseMediaModel
{
    protected $table = 'bd_productos_regulaciones_antidumping_media';
    
    protected $fillable = [
        'id_regulacion',
        'extension',
        'peso',
        'nombre_original',
        'ruta'
    ];

    /**
     * Obtener la regulación antidumping asociada a este archivo
     */
    public function regulacion(): BelongsTo
    {
        return $this->belongsTo(ProductoRegulacionAntidumping::class, 'id_regulacion', 'id');
    }


} 