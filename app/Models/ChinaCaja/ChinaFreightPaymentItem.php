<?php

namespace App\Models\ChinaCaja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Flete de un contenedor dentro de un pago.
 *
 * @property int $id
 * @property int $payment_id
 * @property int $container_id
 * @property float $usd_amount
 * @property ChinaFreightPayment $payment
 */
class ChinaFreightPaymentItem extends Model
{
    protected $table = 'china_freight_payment_items';

    public $timestamps = false;

    protected $fillable = [
        'payment_id',
        'container_id',
        'usd_amount',
    ];

    protected $casts = [
        'usd_amount' => 'float',
    ];

    /** @return BelongsTo<ChinaFreightPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(ChinaFreightPayment::class, 'payment_id');
    }
}
