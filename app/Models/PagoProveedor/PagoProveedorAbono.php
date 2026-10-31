<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $request_id
 * @property float $amount
 * @property float|null $bank_amount
 * @property float|null $recorded_amount
 * @property \Illuminate\Support\Carbon|null $paid_on
 * @property string $operation_number
 * @property string $bank
 * @property bool $interbank
 * @property string $voucher_path
 * @property string $voucher_name
 * @property string $status
 * @property string|null $observation_reason
 * @property string|null $observation_detail
 * @property bool $corrected_by_admin
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property int|null $verified_by_user_id
 * @property float $surplus
 * @property string|null $surplus_decision
 * @property bool $repeated_operation
 * @property int|null $user_id
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorAbonoAplicacion> $aplicaciones
 * @property PagoProveedorSolicitud|null $solicitud
 */
class PagoProveedorAbono extends Model
{
    public const STATUS_IN_VALIDATION = 'en_validacion';
    public const STATUS_VALIDATED = 'validado';
    public const STATUS_OBSERVED = 'observado';
    public const STATUS_CANCELLED = 'anulado';
    public const BANK_BCP = 'bcp';
    public const BANK_INTERBANK = 'interbank';
    public const EXCESS_TO_CONSULT = 'por_consultar';
    public const EXCESS_APPLIED = 'aplicado_cuota';
    public const EXCESS_REFUND = 'reembolso_solicitado';
    public const EXCESS_KEEP = 'no_devuelve';
    public const REASON_NOT_RECEIVED = 'did_not_arrive';
    public const REASON_OTHER_ACCOUNT = 'other_account';
    public const REASON_FAKE_VOUCHER = 'fake_voucher';
    public const FILTER_ALL = 'all';
    public const SETTING_OBSERVATION_REASONS = 'deposit_observation_reasons';
    public const PURPOSE_CLIENT = 'client_deposit';
    public const PURPOSE_EXCHANGE = 'exchange_difference';

    protected $table = 'pago_proveedor_abonos';

    protected $fillable = [
        'request_id',
        'purpose',
        'exchange_provider_id',
        'amount',
        'bank_amount',
        'recorded_amount',
        'paid_on',
        'operation_number',
        'bank',
        'interbank',
        'voucher_path',
        'voucher_name',
        'status',
        'observation_reason',
        'observation_detail',
        'corrected_by_admin',
        'verified_at',
        'verified_by_user_id',
        'surplus',
        'surplus_decision',
        'repeated_operation',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'float',
        'bank_amount' => 'float',
        'recorded_amount' => 'float',
        'paid_on' => 'date:Y-m-d',
        'corrected_by_admin' => 'boolean',
        'verified_at' => 'datetime',
        'interbank' => 'boolean',
        'surplus' => 'float',
        'repeated_operation' => 'boolean',
    ];

    /**
     * @return BelongsTo<PagoProveedorSolicitud, $this>
     */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorSolicitud::class, 'request_id');
    }

    /**
     * @return HasMany<PagoProveedorAbonoAplicacion, $this>
     */
    public function aplicaciones(): HasMany
    {
        return $this->hasMany(PagoProveedorAbonoAplicacion::class, 'deposit_id');
    }

    /**
     * @return BelongsTo<PagoProveedorSolicitudProveedor, $this>
     */
    public function exchangeProvider(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorSolicitudProveedor::class, 'exchange_provider_id');
    }
}
