<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $request_provider_id
 * @property int $installment
 * @property int $supplier_payment
 * @property float $cargo_amount
 * @property float $charged_bank_fee
 * @property float $real_bank_fee
 * @property float $charged_admin_fee
 * @property float $bank_difference
 * @property float $profit
 * @property string $receipt_path
 * @property string $receipt_name
 * @property \Illuminate\Support\Carbon|null $executed_on
 * @property int|null $user_id
 * @property PagoProveedorSolicitudProveedor|null $proveedor
 */
class PagoProveedorEjecucion extends Model
{
    public const BUCKET_PENDING = 'pending';
    public const BUCKET_PAID = 'paid';
    public const STATUS_PENDING = 'pendiente';
    public const STATUS_PAID = 'pagado';
    public const RESULT_WITHIN = 'within_tolerance';
    public const RESULT_COLLECT = 'to_collect';
    public const RESULT_REFUND = 'to_refund';

    protected $table = 'pago_proveedor_ejecuciones';

    protected $fillable = [
        'request_provider_id',
        'installment',
        'supplier_payment',
        'cargo_amount',
        'charged_bank_fee',
        'real_bank_fee',
        'charged_admin_fee',
        'bank_difference',
        'profit',
        'real_exchange_rate',
        'yuan_obtained',
        'yuan_paid',
        'exchange_difference_usd',
        'exchange_result',
        'receipt_path',
        'receipt_name',
        'executed_on',
        'user_id',
    ];

    protected $casts = [
        'cargo_amount' => 'float',
        'charged_bank_fee' => 'float',
        'real_bank_fee' => 'float',
        'charged_admin_fee' => 'float',
        'bank_difference' => 'float',
        'profit' => 'float',
        'real_exchange_rate' => 'float',
        'yuan_obtained' => 'float',
        'yuan_paid' => 'float',
        'exchange_difference_usd' => 'float',
        'executed_on' => 'date:Y-m-d',
    ];

    /**
     * @return BelongsTo<PagoProveedorSolicitudProveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorSolicitudProveedor::class, 'request_provider_id');
    }
}
