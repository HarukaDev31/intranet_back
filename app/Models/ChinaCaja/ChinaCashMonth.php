<?php

namespace App\Models\ChinaCaja;

use Illuminate\Database\Eloquent\Model;

/**
 * TC, saldo inicial y cierre de un mes del Flujo de caja.
 *
 * @property int $id
 * @property string $month
 * @property float|null $exchange_rate
 * @property float|null $opening_balance
 * @property float|null $closing_balance
 * @property float|null $income_total
 * @property float|null $expense_total
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property int|null $closed_by
 */
class ChinaCashMonth extends Model
{
    protected $table = 'china_cash_months';

    protected $fillable = [
        'month',
        'exchange_rate',
        'opening_balance',
        'closing_balance',
        'income_total',
        'expense_total',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'exchange_rate' => 'float',
        'opening_balance' => 'float',
        'closing_balance' => 'float',
        'income_total' => 'float',
        'expense_total' => 'float',
        'closed_at' => 'datetime',
    ];
}
