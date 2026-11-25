<?php

namespace App\Models\ChinaCaja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pago de uno o varios fletes; entra al Flujo de caja como egreso en RMB.
 *
 * @property int $id
 * @property string $paid_on
 * @property bool $manual
 * @property float $exchange_rate
 * @property float $usd_amount
 * @property float $yuan_amount
 * @property string|null $voucher_path
 * @property string|null $voucher_name
 * @property int|null $created_by
 */
class ChinaFreightPayment extends Model
{
    protected $table = 'china_freight_payments';

    protected $fillable = [
        'paid_on',
        'manual',
        'exchange_rate',
        'usd_amount',
        'yuan_amount',
        'voucher_path',
        'voucher_name',
        'created_by',
    ];

    protected $casts = [
        'manual' => 'boolean',
        'exchange_rate' => 'float',
        'usd_amount' => 'float',
        'yuan_amount' => 'float',
    ];

    /** @return HasMany<ChinaFreightPaymentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ChinaFreightPaymentItem::class, 'payment_id');
    }
}
