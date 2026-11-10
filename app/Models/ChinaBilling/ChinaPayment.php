<?php

namespace App\Models\ChinaBilling;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cobro del cliente (PG-0001). Estados derivados: por confirmar → confirmado → en caja.
 *
 * @property int $id
 * @property string $code
 * @property int $organization_id
 * @property float $amount_sent
 * @property float|null $amount_received
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $cashed_at
 * @property int|null $shortfall_operation_id
 */
class ChinaPayment extends Model
{
    public const BY_PARTNER = 'partner';

    public const BY_CHINA = 'china';

    protected $table = 'china_payments';

    protected $fillable = [
        'code', 'organization_id', 'paid_on', 'amount_sent', 'receipt_path', 'receipt_name', 'registered_by',
        'registered_by_user_id', 'amount_received', 'confirmed_at', 'confirmed_by', 'shortfall_operation_id',
        'exchange_rate', 'yuan_amount', 'cash_voucher_path', 'cash_voucher_name', 'cashed_at', 'cashed_by',
    ];

    protected $casts = [
        'paid_on' => 'date',
        'amount_sent' => 'float',
        'amount_received' => 'float',
        'exchange_rate' => 'float',
        'yuan_amount' => 'float',
        'confirmed_at' => 'datetime',
        'cashed_at' => 'datetime',
    ];

    /** @return HasMany<ChinaPaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ChinaPaymentAllocation::class, 'payment_id');
    }

    /** @return HasMany<ChinaCreditApplication, $this> */
    public function creditApplications(): HasMany
    {
        return $this->hasMany(ChinaCreditApplication::class, 'payment_id');
    }
}
