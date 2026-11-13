<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $deposit_id
 * @property int $request_provider_id
 * @property float $amount
 * @property float|null $recorded_amount  Lo que aplicó Ventas cuando Administración lo ajustó al importe del banco
 * @property PagoProveedorAbono|null $abono
 * @property PagoProveedorSolicitudProveedor|null $proveedor
 */
class PagoProveedorAbonoAplicacion extends Model
{
    protected $table = 'pago_proveedor_abono_aplicaciones';

    protected $fillable = [
        'deposit_id',
        'request_provider_id',
        'amount',
        'recorded_amount',
        'continues',
    ];

    protected $casts = [
        'amount' => 'float',
        'recorded_amount' => 'float',
    ];

    /**
     * @return BelongsTo<PagoProveedorAbono, $this>
     */
    public function abono(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorAbono::class, 'deposit_id');
    }

    /**
     * @return BelongsTo<PagoProveedorSolicitudProveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorSolicitudProveedor::class, 'request_provider_id');
    }
}
