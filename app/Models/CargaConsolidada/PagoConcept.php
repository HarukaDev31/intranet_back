<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;

class PagoConcept extends Model
{
    protected $table = 'cotizacion_coordinacion_pagos_concept';
    
    protected $fillable = [
        'name',
        'description'
    ];

    /**
     * Relación con pagos
     */
    public function pagos()
    {
        return $this->hasMany(Pago::class, 'id_concept');
    }

    /**
     * Constantes para conceptos de pago
     */
    public const CONCEPT_PAGO_LOGISTICA = 1;
    public const CONCEPT_PAGO_IMPUESTOS = 2;
    public const CONCEPT_PAGO_DELIVERY = 3;

    /**
     * Obtener concepto por nombre
     */
    public static function getByName($name)
    {
        return static::where('name', $name)->first();
    }
} 