<?php

namespace App\Models\BaseDatos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\BaseDatos\ProductoRegulacionPermiso;
use App\Models\BaseDatos\ProductoRegulacionEtiquetado;

class EntidadReguladora extends Model
{
    protected $table = 'bd_entidades_reguladoras';
    
    protected $fillable = [
        'nombre',
        'descripcion'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtener las regulaciones de permisos asociadas a esta entidad
     */
    public function regulacionesPermiso(): HasMany
    {
        return $this->hasMany(ProductoRegulacionPermiso::class, 'id_entidad_reguladora', 'id');
    }

    /**
     * Obtener las regulaciones de etiquetado asociadas a esta entidad
     */
    public function regulacionesEtiquetado(): HasMany
    {
        return $this->hasMany(ProductoRegulacionEtiquetado::class, 'id_entidad_reguladora', 'id');
    }
} 