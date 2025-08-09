<?php

namespace App\Models\BaseDatos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\BaseDatos\Regulaciones\ProductoRubro;

class ProductoRegulacionPermiso extends Model
{
    protected $table = 'bd_productos_regulaciones_permiso';
    
    protected $fillable = [
        'id_rubro',
        'id_entidad_reguladora',
        'nombre',
        'c_permiso',
        'c_tramitador',
        'observaciones'
    ];

    protected $casts = [
        'c_permiso' => 'float',
        'c_tramitador' => 'float',
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
        return $this->hasMany(ProductoRegulacionPermisoMedia::class, 'id_regulacion', 'id');
    }
} 