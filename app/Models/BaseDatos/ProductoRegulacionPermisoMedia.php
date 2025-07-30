<?php

namespace App\Models\BaseDatos;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoRegulacionPermisoMedia extends BaseMediaModel
{
    protected $table = 'bd_productos_regulaciones_permiso_media';
    
    protected $fillable = [
        'id_regulacion',
        'extension',
        'peso',
        'nombre_original',
        'ruta'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtener la regulación de permiso asociada a este archivo
     */
    public function regulacion(): BelongsTo
    {
        return $this->belongsTo(ProductoRegulacionPermiso::class, 'id_regulacion', 'id');
    }
} 