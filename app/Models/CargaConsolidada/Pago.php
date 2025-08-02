<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $table = 'contenedor_consolidado_cotizacion_coordinacion_pagos';
    
    protected $fillable = [
        'id_contenedor',
        'id_cotizacion',
        'id_concept',
        'monto',
        'voucher_url',
        'payment_date',
        'banco',
        'is_confirmed',
        'status'
    ];

    protected $casts = [
        'monto' => 'decimal:4',
        'payment_date' => 'date',
        'is_confirmed' => 'boolean',
        'created_at' => 'datetime'
    ];

    /**
     * Constantes para estados de pago
     */
    public const ESTADOS = [
        'PENDIENTE' => 'PENDIENTE',
        'CONFIRMADO' => 'CONFIRMADO',
        'OBSERVADO' => 'OBSERVADO'
    ];

    /**
     * Relación con cotización
     */
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    /**
     * Relación con concepto de pago
     */
    public function concepto()
    {
        return $this->belongsTo(PagoConcept::class, 'id_concept');
    }

    /**
     * Scope para filtrar por estado
     */
    public function scopePorEstado($query, $estado)
    {
        return $query->where('status', $estado);
    }

    /**
     * Scope para filtrar por concepto
     */
    public function scopePorConcepto($query, $concepto)
    {
        return $query->whereHas('concepto', function($q) use ($concepto) {
            $q->where('name', $concepto);
        });
    }

    /**
     * Scope para filtrar por cotización
     */
    public function scopePorCotizacion($query, $idCotizacion)
    {
        return $query->where('id_cotizacion', $idCotizacion);
    }
} 