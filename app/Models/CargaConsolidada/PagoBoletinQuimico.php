<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;

class PagoBoletinQuimico extends Model
{
    protected $table = 'pagos_boletin_quimico';

    protected $fillable = [
        'id_boletin_quimico_item',
        'monto',
        'voucher_url',
        'payment_date',
        'banco',
        'status',
        'confirmation_date',
        'created_by',
    ];

    protected $casts = [
        'monto' => 'decimal:4',
        'confirmation_date' => 'datetime',
    ];

    public const STATUS_PENDIENTE = 'PENDIENTE';
    public const STATUS_CONFIRMADO = 'CONFIRMADO';
    public const STATUS_OBSERVADO = 'OBSERVADO';

    public function boletinQuimicoItem()
    {
        return $this->belongsTo(BoletinQuimicoCotizacionItem::class, 'id_boletin_quimico_item');
    }
}
