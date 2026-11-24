<?php

namespace App\Models\ChinaCaja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Costo fijo mensual en RMB.
 *
 * @property int $id
 * @property string $name
 * @property float $amount
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ChinaFixedCost extends Model
{
    use SoftDeletes;

    protected $table = 'china_fixed_costs';

    protected $fillable = [
        'name',
        'amount',
        'active',
    ];

    protected $casts = [
        'amount' => 'float',
        'active' => 'boolean',
    ];
}
