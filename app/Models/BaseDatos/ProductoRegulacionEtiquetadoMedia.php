<?php

namespace App\Models\BaseDatos;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\BaseDatos\Regulaciones\ProductoRubro;
class ProductoRegulacionEtiquetadoMedia extends BaseMediaModel
{
    protected $table = 'bd_productos_regulaciones_etiquetado_media';
    
    protected $fillable = [
        'id_rubro',
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
     * Obtener el rubro asociado a este archivo
     */
    public function rubro(): BelongsTo
    {
        return $this->belongsTo(ProductoRubro::class, 'id_rubro', 'id');
    }

    /**
     * Obtener la regulación de etiquetado asociada a este archivo
     */
    public function regulacion(): BelongsTo
    {
        return $this->belongsTo(ProductoRegulacionEtiquetado::class, 'id_regulacion', 'id');
    }
} 