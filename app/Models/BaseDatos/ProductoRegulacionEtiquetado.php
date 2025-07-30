<?php

namespace App\Models\BaseDatos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoRegulacionEtiquetado extends Model
{
    protected $table = 'bd_productos_regulaciones_etiquetado';
    
    protected $fillable = [
        'id_rubro',
        'id_entidad_reguladora',
        'observaciones'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtener el rubro asociado a esta regulación
     */
    public function rubro(): BelongsTo
    {
        return $this->belongsTo(ProductoRubro::class, 'id_rubro', 'id');
    }

    /**
     * Obtener la entidad reguladora asociada a esta regulación
     */
    public function entidadReguladora(): BelongsTo
    {
        return $this->belongsTo(EntidadReguladora::class, 'id_entidad_reguladora', 'id');
    }

    /**
     * Obtener los archivos multimedia asociados a esta regulación
     */
    public function media(): HasMany
    {
        return $this->hasMany(ProductoRegulacionEtiquetadoMedia::class, 'id_regulacion', 'id');
    }
} 