<?php

namespace App\Models\ChinaBilling;

use Illuminate\Database\Eloquent\Model;

/**
 * Saldo a favor de un cobro aplicado a una operación.
 *
 * @property int $payment_id
 * @property int $operation_id
 * @property float $amount
 * @property int|null $from_operation_id
 */
class ChinaCreditApplication extends Model
{
    protected $table = 'china_credit_applications';

    protected $fillable = ['payment_id', 'operation_id', 'amount', 'applied_on', 'from_operation_id'];

    protected $casts = ['amount' => 'float', 'applied_on' => 'date'];
}
