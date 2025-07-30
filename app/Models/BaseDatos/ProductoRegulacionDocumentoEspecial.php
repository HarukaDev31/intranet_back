<?php

namespace App\Models\BaseDatos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoRegulacionDocumentoEspecial extends Model
{
    protected $table = 'bd_productos_regulaciones_documentos_especiales';
    
    protected $fillable = [
        'id_rubro',
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
     * Obtener los archivos multimedia asociados a esta regulación
     */
    public function media(): HasMany
    {
        return $this->hasMany(ProductoRegulacionDocumentoEspecialMedia::class, 'id_regulacion', 'id');
    }
} 