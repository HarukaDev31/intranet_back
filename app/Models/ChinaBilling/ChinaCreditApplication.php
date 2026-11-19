<?php

namespace App\Models\ChinaBilling;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saldo a favor de un cobro aplicado a una operación.
 *
 * @property int $id
 * @property int $payment_id
 * @property int $operation_id
 * @property float $amount
 * @property \Illuminate\Support\Carbon|null $applied_on
 * @property int|null $from_operation_id
 * @property-read ChinaPayment|null $payment
 */
class ChinaCreditApplication extends Model
{
    protected $table = 'china_credit_applications';

    protected $fillable = ['payment_id', 'operation_id', 'amount', 'applied_on', 'from_operation_id'];

    protected $casts = ['amount' => 'float', 'applied_on' => 'date'];

    /** @return BelongsTo<ChinaPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(ChinaPayment::class, 'payment_id');
    }
}
