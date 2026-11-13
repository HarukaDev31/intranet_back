<?php

namespace App\Models\ChinaBilling;

use Illuminate\Database\Eloquent\Model;

/**
 * Costo adicional de una operación (incluye "Diferencia OP-xxxx" trasladada).
 *
 * @property int $id
 * @property int $operation_id
 * @property string $name
 * @property float $amount
 * @property int|null $source_operation_id
 */
class ChinaOperationCost extends Model
{
    protected $table = 'china_operation_costs';

    protected $fillable = ['operation_id', 'name', 'amount', 'source_operation_id'];

    protected $casts = ['amount' => 'float'];
}
