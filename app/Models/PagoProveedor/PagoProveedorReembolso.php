<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $request_id
 * @property int|null $request_provider_id
 * @property int|null $deposit_id
 * @property string $origin
 * @property string $status
 * @property float $amount
 * @property array<string, mixed>|null $breakdown
 * @property string|null $reason
 * @property PagoProveedorSolicitud|null $solicitud
 * @property PagoProveedorSolicitudProveedor|null $proveedor
 */
class PagoProveedorReembolso extends Model
{
    public const STATUS_CONSULT = 'por_consultar';
    public const STATUS_REQUESTED = 'solicitado';
    public const STATUS_APPROVED = 'aprobado';
    public const STATUS_REJECTED = 'rechazado';
    public const ORIGIN_SURPLUS = 'surplus';
    public const ORIGIN_EXCHANGE = 'exchange';
    public const ORIGIN_PAYMENT = 'payment_2';
    public const OPEN_STATUSES = [self::STATUS_CONSULT, self::STATUS_REQUESTED];

    protected $table = 'pago_proveedor_reembolsos';

    protected $fillable = [
        'request_id',
        'request_provider_id',
        'deposit_id',
        'origin',
        'status',
        'amount',
        'breakdown',
        'reason',
        'requested_at',
        'paid_on',
        'bank',
        'operation_number',
        'approved_amount',
        'voucher_path',
        'voucher_name',
        'decided_at',
        'decided_by_user_id',
    ];

    protected $casts = [
        'amount' => 'float',
        'approved_amount' => 'float',
        'breakdown' => 'array',
        'requested_at' => 'datetime',
        'paid_on' => 'date:Y-m-d',
        'decided_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<PagoProveedorSolicitud, $this>
     */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorSolicitud::class, 'request_id');
    }

    /**
     * @return BelongsTo<PagoProveedorSolicitudProveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorSolicitudProveedor::class, 'request_provider_id');
    }
}
