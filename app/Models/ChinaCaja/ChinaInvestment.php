<?php

namespace App\Models\ChinaCaja;

use Illuminate\Database\Eloquent\Model;

/**
 * Inversión anual en RMB.
 *
 * @property int $id
 * @property string $name
 * @property float $amount
 * @property string $due_on
 * @property string|null $paid_on
 * @property int|null $previous_id
 */
class ChinaInvestment extends Model
{
    protected $table = 'china_investments';

    protected $fillable = [
        'name',
        'amount',
        'due_on',
        'paid_on',
        'previous_id',
    ];

    protected $casts = [
        'amount' => 'float',
    ];
}
