<?php

namespace App\Models\ChinaCaja;

use Illuminate\Database\Eloquent\Model;

/**
 * Cierre semanal: saldo del banco y pantallazo.
 *
 * @property int $id
 * @property string $month
 * @property string $starts_on
 * @property string $ends_on
 * @property float $balance
 * @property float $usd_pending
 * @property string $screenshot_path
 * @property string $screenshot_name
 * @property int|null $closed_by
 */
class ChinaCashWeek extends Model
{
    protected $table = 'china_cash_weeks';

    protected $fillable = [
        'month',
        'starts_on',
        'ends_on',
        'balance',
        'usd_pending',
        'screenshot_path',
        'screenshot_name',
        'closed_by',
    ];

    protected $casts = [
        'balance' => 'float',
        'usd_pending' => 'float',
    ];
}
