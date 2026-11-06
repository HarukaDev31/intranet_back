<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dinero que China recibe del socio para una solicitud y lo cambia a yuanes.
 *
 * @property int $id
 * @property int $request_id
 * @property float $usd_amount
 * @property float $exchange_rate
 * @property float $yuan_amount
 * @property \Illuminate\Support\Carbon $received_on
 * @property string|null $receipt_path
 * @property string|null $receipt_name
 * @property int|null $user_id
 * @property PagoProveedorSolicitud|null $solicitud
 */
class PagoProveedorIngreso extends Model
{
    protected $table = 'pago_proveedor_ingresos';

    protected $fillable = [
        'request_id',
        'usd_amount',
        'exchange_rate',
        'yuan_amount',
        'received_on',
        'receipt_path',
        'receipt_name',
        'user_id',
    ];

    protected $casts = [
        'usd_amount' => 'float',
        'exchange_rate' => 'float',
        'yuan_amount' => 'float',
        'received_on' => 'date',
    ];

    /**
     * @return BelongsTo<PagoProveedorSolicitud, $this>
     */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorSolicitud::class, 'request_id');
    }
}
