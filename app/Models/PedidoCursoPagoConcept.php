<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoCursoPagoConcept extends Model
{
    protected $table = 'pedido_curso_pagos_concept';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'name',
        'description'
    ];

    /**
     * Constantes para los conceptos de pago
     */
    const CONCEPT_PAGO_ADELANTO_CURSO = 1; // Asumiendo que ADELANTO tiene ID 1

    /**
     * Relación con PedidoCursoPago
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(PedidoCursoPago::class, 'id_concept', 'id');
    }
} 