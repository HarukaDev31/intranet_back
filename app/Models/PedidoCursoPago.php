<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoCursoPago extends Model
{
    protected $table = 'pedido_curso_pagos';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'id_pedido_curso',
        'id_concept',
        'monto',
        'status',
        'payment_date'
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'payment_date' => 'datetime'
    ];

    /**
     * Relación con PedidoCurso
     */
    public function pedidoCurso(): BelongsTo
    {
        return $this->belongsTo(PedidoCurso::class, 'id_pedido_curso', 'ID_Pedido_Curso');
    }

    /**
     * Relación con PedidoCursoPagoConcept
     */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(PedidoCursoPagoConcept::class, 'id_concept', 'id');
    }
} 