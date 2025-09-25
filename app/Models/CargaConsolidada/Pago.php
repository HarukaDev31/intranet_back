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
        'status',
        'confirmation_date'
    ];

    protected $casts = [
        'monto' => 'decimal:4',
        'payment_date' => 'date',
        'confirmation_date' => 'datetime',
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

    /**
     * Boot del modelo para manejar eventos
     */
    protected static function boot()
    {
        parent::boot();

        // Evento que se ejecuta antes de actualizar
        static::updating(function ($pago) {
            // Si el status está cambiando a CONFIRMADO
            if ($pago->isDirty('status') && $pago->status === self::ESTADOS['CONFIRMADO']) {
                $pago->confirmation_date = now();
            }
            // Si el status cambia de CONFIRMADO a otro, limpiar la fecha
            elseif ($pago->isDirty('status') && $pago->getOriginal('status') === self::ESTADOS['CONFIRMADO'] && $pago->status !== self::ESTADOS['CONFIRMADO']) {
                $pago->confirmation_date = null;
            }
        });

        // Evento que se ejecuta antes de crear
        static::creating(function ($pago) {
            // Si se está creando directamente con status CONFIRMADO
            if ($pago->status === self::ESTADOS['CONFIRMADO']) {
                $pago->confirmation_date = now();
            }
        });
    }

    /**
     * Confirmar el pago
     */
    public function confirmar()
    {
        $this->status = self::ESTADOS['CONFIRMADO'];
        $this->confirmation_date = now();
        return $this->save();
    }

    /**
     * Verificar si el pago está confirmado
     */
    public function estaConfirmado()
    {
        return $this->status === self::ESTADOS['CONFIRMADO'];
    }

    /**
     * Scope para filtrar pagos confirmados
     */
    public function scopeConfirmados($query)
    {
        return $query->where('status', self::ESTADOS['CONFIRMADO']);
    }

    /**
     * Scope para filtrar por fecha de confirmación
     */
    public function scopeConfirmadosEntre($query, $fechaInicio, $fechaFin)
    {
        return $query->where('status', self::ESTADOS['CONFIRMADO'])
                    ->whereBetween('confirmation_date', [$fechaInicio, $fechaFin]);
    }
} 