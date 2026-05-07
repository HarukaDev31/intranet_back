<?php

namespace App\Models;

use App\Support\MonetarioDosDecimales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Viatico extends Model
{
    use SoftDeletes;

    protected $table = 'viaticos';
    
    protected $fillable = [
        'subject',
        'reimbursement_date',
        'return_date',
        'requesting_area',
        'expense_description',
        'total_amount',
        'status',
        'codigo_confirmado',
        'receipt_file',
        'payment_receipt_file',
        'user_id'
    ];

    protected $casts = [
        'reimbursement_date' => 'date',
        'return_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Misma lógica que viaticos_pagos.monto (evitar cast decimal:2 + float IEEE).
     */
    public function setTotalAmountAttribute($value): void
    {
        $this->attributes['total_amount'] = MonetarioDosDecimales::paraBd($value);
    }

    // Estados disponibles
    const STATUS_PENDING = 'PENDING';
    const STATUS_CONFIRMED = 'CONFIRMED';
    const STATUS_REJECTED = 'REJECTED';

    /**
     * Relación con Usuario
     */
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'user_id', 'ID_Usuario');
    }

    /**
     * Relación con items de pago (viaticos_pagos)
     */
    public function pagos()
    {
        return $this->hasMany(ViaticoPago::class);
    }

    /**
     * Relación con comprobantes de retribución (múltiples)
     */
    public function retribuciones()
    {
        return $this->hasMany(ViaticoRetribucion::class)->orderBy('orden')->orderBy('id');
    }

    /**
     * Obtener estados disponibles
     */
    public static function getEstadosDisponibles()
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_CONFIRMED => 'Confirmado',
            self::STATUS_REJECTED => 'Rechazado'
        ];
    }

    /**
     * Scope para filtrar por estado
     */
    public function scopePorEstado($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para viáticos del usuario
     */
    public function scopeDelUsuario($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para viáticos pendientes o rechazados
     */
    public function scopePendientesORechazados($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_REJECTED]);
    }

    /**
     * Scope para viáticos confirmados
     */
    public function scopeConfirmados($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }
}
