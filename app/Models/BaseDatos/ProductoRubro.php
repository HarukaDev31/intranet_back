<?php

namespace App\Models\BaseDatos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * Constantes para los tipos de productos
     */
    const TIPO_ANTIDUMPING = 'ANTIDUMPING';
    const TIPO_DOCUMENTO_ESPECIAL = 'DOCUMENTO_ESPECIAL';
    const TIPO_ETIQUETADO = 'ETIQUETADO';

    /**
     * Obtener los tipos permitidos
     */
    public static function getTiposPermitidos(): array
    {
        return [
            self::TIPO_ANTIDUMPING,
            self::TIPO_DOCUMENTO_ESPECIAL,
            self::TIPO_ETIQUETADO
        ];
    }

    /**
     * Scope para filtrar por tipo
     */
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope para productos antidumping
     */
    public function scopeAntidumping($query)
    {
        return $query->where('tipo', self::TIPO_ANTIDUMPING);
    }

    /**
     * Scope para productos con documentos especiales
     */
    public function scopeDocumentoEspecial($query)
    {
        return $query->where('tipo', self::TIPO_DOCUMENTO_ESPECIAL);
    }

    /**
     * Scope para productos con etiquetado
     */
    public function scopeEtiquetado($query)
    {
        return $query->where('tipo', self::TIPO_ETIQUETADO);
    }

    /**
     * Verificar si es tipo antidumping
     */
    public function getEsAntidumpingAttribute(): bool
    {
        return $this->tipo === self::TIPO_ANTIDUMPING;
    }

    /**
     * Verificar si es tipo documento especial
     */
    public function getEsDocumentoEspecialAttribute(): bool
    {
        return $this->tipo === self::TIPO_DOCUMENTO_ESPECIAL;
    }

    /**
     * Verificar si es tipo etiquetado
     */
    public function getEsEtiquetadoAttribute(): bool
    {
        return $this->tipo === self::TIPO_ETIQUETADO;
    }

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