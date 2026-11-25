<?php

namespace App\Models\ChinaCaja;

use Illuminate\Database\Eloquent\Model;

/**
 * Descuento de un costo fijo en un mes (no cambia si luego se edita o elimina el costo).
 *
 * @property int $id
 * @property string $month
 * @property int $fixed_cost_id
 * @property string $name
 * @property float $amount
 * @property string $charged_on
 */
class ChinaFixedCostCharge extends Model
{
    protected $table = 'china_fixed_cost_charges';

    protected $fillable = [
        'month',
        'fixed_cost_id',
        'name',
        'amount',
        'charged_on',
    ];

    protected $casts = [
        'amount' => 'float',
    ];
}
