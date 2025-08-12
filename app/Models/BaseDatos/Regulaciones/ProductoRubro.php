<?php

namespace App\Models\BaseDatos\Regulaciones;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\BaseDatos\ProductoRegulacionAntidumping;
use App\Models\BaseDatos\ProductoRegulacionPermiso;
use App\Models\BaseDatos\ProductoRegulacionEtiquetado;
use App\Models\BaseDatos\ProductoRegulacionDocumentoEspecial;
class ProductoRubro extends Model
{
    protected $table = 'bd_productos';
    
    protected $fillable = [
        'nombre',
        'tipo'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    const TIPO_ANTIDUMPING = 'ANTIDUMPING';
    const TIPO_DOCUMENTO_ESPECIAL = 'DOCUMENTO_ESPECIAL';
    const TIPO_ETIQUETADO = 'ETIQUETADO';
    /**
     * Obtener las regulaciones antidumping asociadas a este rubro
     */
    public function regulacionesAntidumping(): HasMany
    {
        return $this->hasMany(ProductoRegulacionAntidumping::class, 'id_rubro', 'id');
    }

    /**
     * Obtener las regulaciones de permisos asociadas a este rubro
     */
    public function regulacionesPermiso(): HasMany
    {
        return $this->hasMany(ProductoRegulacionPermiso::class, 'id_rubro', 'id');
    }

    /**
     * Obtener las regulaciones de etiquetado asociadas a este rubro
     */
    public function regulacionesEtiquetado(): HasMany
    {
        return $this->hasMany(ProductoRegulacionEtiquetado::class, 'id_rubro', 'id');
    }

    /**
     * Obtener las regulaciones de documentos especiales asociadas a este rubro
     */
    public function regulacionesDocumentosEspeciales(): HasMany
    {
        return $this->hasMany(ProductoRegulacionDocumentoEspecial::class, 'id_rubro', 'id');
    }
} 
