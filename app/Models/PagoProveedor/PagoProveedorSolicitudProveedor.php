<?php

namespace App\Models\PagoProveedor;

use App\Models\CargaConsolidada\CotizacionProveedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $request_id
 * @property int $position
 * @property string|null $code_supplier
 * @property int|null $consolidated_provider_id
 * @property string|null $supplier_name
 * @property string|null $product
 * @property string $currency
 * @property string $payment_type
 * @property float $cargo_value_usd
 * @property string $client_payment_mode
 * @property string $supplier_payment_mode
 * @property float $first_share_percent
 * @property float|null $second_share_percent
 * @property \Illuminate\Support\Carbon|null $second_installment_on
 * @property bool $charges_bank_fee
 * @property bool $charges_admin_fee
 * @property float $bank_fee_amount
 * @property float $admin_fee_amount
 * @property float $total_amount
 * @property int|null $bank_fee_on_position
 * @property array<string, mixed>|null $summary_json
 * @property string|null $proforma_path
 * @property string|null $proforma_name
 * @property string|null $request_code
 * @property int $stage
 * @property string $status
 * @property int $current_installment
 * @property int $current_payment
 * @property string|null $approval_status
 * @property string|null $approval_mode
 * @property float|null $assumed_amount
 * @property string|null $assumed_reason
 * @property float|null $collect_amount
 * @property \Illuminate\Support\Carbon|null $collect_until
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property int|null $approved_by_user_id
 * @property string|null $second_installment_choice
 * @property \Illuminate\Support\Carbon|null $decision_due_at
 * @property bool $exchange_rate_pending
 * @property PagoProveedorSolicitud|null $solicitud
 * @property PagoProveedorDatoProveedor|null $datos
 * @property CotizacionProveedor|null $consolidatedProvider
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorAbonoAplicacion> $aplicaciones
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorEjecucion> $ejecuciones
 */
class PagoProveedorSolicitudProveedor extends Model
{
    public const STAGE_DATA = 1;
    public const STAGE_VALIDATION = 2;
    public const STAGE_EXECUTION = 3;
    public const STAGE_CONFIRMATION = 4;
    public const FIRST_INSTALLMENT = 1;

    public const STATUS_IN_PROGRESS = 'en_proceso';
    public const STATUS_OBSERVED = 'observado';
    public const STATUS_CANCELLED = 'anulado';
    public const STATUS_COMPLETED = 'completado';

    public const TASK_PROVIDER_DATA = 'provider_data';
    public const TASK_RECEIPT = 'receipt';
    public const TASK_OBSERVED = 'observed';
    public const TASK_CONFIRMATION = 'confirmation';
    public const CHOICE_PROCEED = 'se_realizara';
    public const CHOICE_CANCEL = 'no_se_realizara';
    public const OWNER_SALES = 'sales';
    public const OWNER_OTHER = 'other';
    public const SHAPE_SINGLE = 'single';
    public const SHAPE_CLIENT_INSTALLMENTS = 'client_installments';
    public const SHAPE_SUPPLIER_INSTALLMENTS = 'supplier_installments';

    public const APPROVAL_PENDING = 'pendiente';
    public const APPROVAL_TO_COLLECT = 'por_cobrar';
    public const APPROVAL_REGISTRATION_ERROR = 'error_registro';
    public const APPROVAL_APPROVED = 'aprobado';
    public const APPROVAL_AUTOMATIC = 'automatica';
    public const APPROVAL_MANUAL = 'manual';
    public const APPROVAL_BUCKET_PENDING = 'pending';
    public const APPROVAL_BUCKET_APPROVED = 'approved';
    public const FILTER_ALL = 'all';
    public const GAP_MATCH = 'match';
    public const GAP_SHORT_TOLERANCE = 'short_tolerance';
    public const GAP_SHORT_EXCEPTION = 'short_exception';
    public const GAP_SHORT = 'short';
    public const GAP_OVER_TOLERANCE = 'over_tolerance';
    public const GAP_OVER = 'over';
    public const MONEY_EPSILON = 0.005;
    public const SETTING_TOLERANCE = 'tolerancia';
    public const SETTING_EXCEPTION_LIMIT = 'limite_excepcional';
    public const SETTING_AUTO_APPROVE = 'aprobacion_automatica';
    public const SETTING_REMEDY_DAYS = 'plazo_subsanar_dias';
    public const SETTING_VERIFY_HOURS = 'plazo_verificacion_horas';
    public const SETTING_UNDO_MINUTES = 'deshacer_sin_aviso_minutos';
    public const SETTING_UNDO_HOURS = 'deshacer_con_aviso_horas';
    public const SETTING_DECISION_HOURS = 'plazo_decision_horas';
    public const SETTING_CHINA_HOURS = 'plazo_observado_china_horas';
    public const SETTING_CHINA_REASONS = 'china_observation_reasons';
    public const PAYMENT_CANCEL = 'anulado';
    public const PAYMENT_REFUND = 'reembolso_solicitado';
    public const CHINA_REASON_WECHAT = 'wechat_wrong';
    public const CHINA_REASON_NO_REPLY = 'no_reply';
    public const CHINA_REASON_MISMATCH = 'data_mismatch';

    protected $table = 'pago_proveedor_solicitud_proveedores';

    protected $fillable = [
        'request_id',
        'position',
        'code_supplier',
        'consolidated_provider_id',
        'supplier_name',
        'product',
        'currency',
        'payment_type',
        'cargo_value_usd',
        'client_payment_mode',
        'supplier_payment_mode',
        'first_share_percent',
        'second_share_percent',
        'second_installment_on',
        'charges_bank_fee',
        'charges_admin_fee',
        'bank_fee_amount',
        'admin_fee_amount',
        'total_amount',
        'bank_fee_on_position',
        'summary_json',
        'proforma_path',
        'proforma_name',
        'request_code',
        'stage',
        'status',
        'current_installment',
        'current_payment',
        'approval_status',
        'approval_mode',
        'assumed_amount',
        'assumed_reason',
        'collect_amount',
        'collect_until',
        'approved_at',
        'approved_by_user_id',
        'second_installment_choice',
        'decision_due_at',
        'exchange_rate_pending',
        'exchange_result',
        'second_payment_choice',
        'china_observation_reason',
        'china_observation_detail',
        'china_observed_at',
        'china_observe_until',
    ];

    protected $casts = [
        'cargo_value_usd' => 'float',
        'first_share_percent' => 'float',
        'second_share_percent' => 'float',
        'second_installment_on' => 'date:Y-m-d',
        'charges_bank_fee' => 'boolean',
        'charges_admin_fee' => 'boolean',
        'bank_fee_amount' => 'float',
        'admin_fee_amount' => 'float',
        'total_amount' => 'float',
        'summary_json' => 'array',
        'assumed_amount' => 'float',
        'collect_amount' => 'float',
        'collect_until' => 'datetime',
        'approved_at' => 'datetime',
        'decision_due_at' => 'datetime',
        'exchange_rate_pending' => 'boolean',
        'china_observed_at' => 'datetime',
        'china_observe_until' => 'datetime',
    ];

    /**
     * @return BelongsTo<PagoProveedorSolicitud, $this>
     */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorSolicitud::class, 'request_id');
    }

    /**
     * @return HasOne<PagoProveedorDatoProveedor, $this>
     */
    public function datos(): HasOne
    {
        return $this->hasOne(PagoProveedorDatoProveedor::class, 'request_provider_id');
    }

    /**
     * The supplier already created in Carga Consolidada.
     *
     * @return BelongsTo<CotizacionProveedor, $this>
     */
    public function consolidatedProvider(): BelongsTo
    {
        return $this->belongsTo(CotizacionProveedor::class, 'consolidated_provider_id');
    }

    /**
     * @return HasMany<PagoProveedorAbonoAplicacion, $this>
     */
    public function aplicaciones(): HasMany
    {
        return $this->hasMany(PagoProveedorAbonoAplicacion::class, 'request_provider_id');
    }

    /**
     * @return HasMany<PagoProveedorEjecucion, $this>
     */
    public function ejecuciones(): HasMany
    {
        return $this->hasMany(PagoProveedorEjecucion::class, 'request_provider_id');
    }

    /**
     * @return HasMany<PagoProveedorAbono, $this>
     */
    public function exchangeDeposits(): HasMany
    {
        return $this->hasMany(PagoProveedorAbono::class, 'exchange_provider_id');
    }

    /**
     * @return HasMany<PagoProveedorReembolso, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(PagoProveedorReembolso::class, 'request_provider_id');
    }

    public function paymentShape(): string
    {
        if ($this->client_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT) {
            return self::SHAPE_CLIENT_INSTALLMENTS;
        }

        if ($this->supplier_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT) {
            return self::SHAPE_SUPPLIER_INSTALLMENTS;
        }

        return self::SHAPE_SINGLE;
    }

    /**
     * Uses the eager-loaded datos relation. Callers list with proveedores.datos.
     */
    public function salesTask(): ?string
    {
        if (in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_COMPLETED], true)) {
            return null;
        }
        if ($this->exchange_rate_pending) {
            return self::TASK_CONFIRMATION;
        }
        if ($this->approval_status === self::APPROVAL_REGISTRATION_ERROR) {
            return self::TASK_OBSERVED;
        }
        if ($this->client_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT
            && (int) $this->current_installment === self::FIRST_INSTALLMENT
            && $this->second_installment_choice === null
            && $this->second_installment_on
            && $this->second_installment_on->copy()->startOfDay()->lte(now('America/Lima')->startOfDay()->addDays(2))) {
            return self::TASK_CONFIRMATION;
        }
        if ((int) $this->stage === self::STAGE_CONFIRMATION
            && $this->client_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT
            && (int) $this->current_installment === self::FIRST_INSTALLMENT
            && $this->second_installment_choice === null) {
            return self::TASK_CONFIRMATION;
        }
        if ((int) $this->stage === self::STAGE_CONFIRMATION
            && $this->currency === PagoProveedorCotizacionProveedor::CURRENCY_RMB
            && $this->supplier_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT
            && (int) $this->current_payment === self::FIRST_INSTALLMENT
            && $this->second_payment_choice === null) {
            return self::TASK_CONFIRMATION;
        }
        if (in_array($this->exchange_result, [PagoProveedorEjecucion::RESULT_COLLECT, PagoProveedorEjecucion::RESULT_REFUND], true)) {
            return self::TASK_CONFIRMATION;
        }
        if ($this->approval_status === self::APPROVAL_TO_COLLECT) {
            return self::TASK_OBSERVED;
        }
        if ($this->status === self::STATUS_OBSERVED) {
            return self::TASK_OBSERVED;
        }
        if ((int) $this->stage !== self::STAGE_DATA) {
            return null;
        }

        $data = $this->datos;
        $ready = $this->currency === PagoProveedorCotizacionProveedor::CURRENCY_RMB
            ? $data && trim((string) $data->seller_name) !== '' && trim((string) $data->wechat) !== ''
            : $data && $data->status === PagoProveedorDatoProveedor::STATUS_RECEIVED;

        return $ready ? self::TASK_RECEIPT : self::TASK_PROVIDER_DATA;
    }
}
