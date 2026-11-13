<?php

namespace App\Models\ChinaBilling;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lo que China le factura a un cliente por un servicio (OP-0001).
 *
 * @property int $id
 * @property string $code
 * @property int $organization_id
 * @property string $service_name
 * @property string $billing_type
 * @property int|null $container_id
 * @property float|null $cbm
 * @property float|null $rate
 * @property float $amount
 * @property float $fob
 * @property float $freight
 * @property int|null $transferred_to_operation_id
 */
class ChinaOperation extends Model
{
    public const BILLING_FIXED = 'fixed';

    public const BILLING_CBM = 'cbm';

    protected $table = 'china_operations';

    protected $fillable = [
        'code', 'organization_id', 'client_id', 'service_name', 'billing_type', 'variant_name',
        'container_id', 'issued_on', 'description', 'cbm', 'rate', 'amount', 'fob',
        'freight', 'packing_list_path', 'packing_list_name', 'transferred_to_operation_id', 'created_by',
    ];

    protected $casts = [
        'issued_on' => 'date',
        'cbm' => 'float',
        'rate' => 'float',
        'amount' => 'float',
        'fob' => 'float',
        'freight' => 'float',
    ];

    /** @return HasMany<ChinaOperationCost, $this> */
    public function costs(): HasMany
    {
        return $this->hasMany(ChinaOperationCost::class, 'operation_id');
    }

    /** @return HasMany<ChinaPaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ChinaPaymentAllocation::class, 'operation_id');
    }

    /** @return HasMany<ChinaCreditApplication, $this> */
    public function creditApplications(): HasMany
    {
        return $this->hasMany(ChinaCreditApplication::class, 'operation_id');
    }
}
