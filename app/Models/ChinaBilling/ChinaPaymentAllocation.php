<?php

namespace App\Models\ChinaBilling;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parte de un cobro aplicada a una operación.
 *
 * @property int $payment_id
 * @property int $operation_id
 * @property float $amount
 */
class ChinaPaymentAllocation extends Model
{
    protected $table = 'china_payment_allocations';

    protected $fillable = ['payment_id', 'operation_id', 'amount'];

    protected $casts = ['amount' => 'float'];

    /** @return BelongsTo<ChinaPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(ChinaPayment::class, 'payment_id');
    }

    /** @return BelongsTo<ChinaOperation, $this> */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(ChinaOperation::class, 'operation_id');
    }
}
