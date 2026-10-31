<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $request_provider_id
 * @property string|null $seller_name
 * @property string|null $wechat
 * @property string $status
 * @property string|null $source
 * @property string $token
 * @property bool $correction_requested
 * @property string|null $beneficiary_name
 * @property string|null $beneficiary_account
 * @property string|null $beneficiary_address
 * @property string|null $beneficiary_country
 * @property string|null $beneficiary_city
 * @property string|null $bank_code_type
 * @property string|null $bank_code
 * @property string|null $bank_name
 * @property string|null $bank_address
 * @property string|null $bank_country
 * @property string|null $bank_city
 * @property bool $uses_intermediary
 * @property string|null $intermediary_code_type
 * @property string|null $intermediary_code
 * @property string|null $intermediary_name
 * @property string|null $intermediary_address
 * @property string|null $intermediary_country
 * @property string|null $intermediary_city
 * @property \Illuminate\Support\Carbon|null $authorized_at
 * @property PagoProveedorSolicitudProveedor|null $proveedor
 */
class PagoProveedorDatoProveedor extends Model
{
    public const STATUS_PENDING = 'pendiente';
    public const STATUS_SENT = 'enviado';
    public const STATUS_AWAITING_AUTHORIZATION = 'por_autorizar';
    public const STATUS_RECEIVED = 'recibido';

    protected $table = 'pago_proveedor_datos_proveedor';

    protected $fillable = [
        'request_provider_id',
        'seller_name',
        'wechat',
        'status',
        'source',
        'token',
        'correction_requested',
        'beneficiary_name',
        'beneficiary_account',
        'beneficiary_address',
        'beneficiary_country',
        'beneficiary_city',
        'bank_code_type',
        'bank_code',
        'bank_name',
        'bank_address',
        'bank_country',
        'bank_city',
        'uses_intermediary',
        'intermediary_code_type',
        'intermediary_code',
        'intermediary_name',
        'intermediary_address',
        'intermediary_country',
        'intermediary_city',
        'authorized_at',
    ];

    protected $casts = [
        'uses_intermediary' => 'boolean',
        'correction_requested' => 'boolean',
        'authorized_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<PagoProveedorSolicitudProveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorSolicitudProveedor::class, 'request_provider_id');
    }
}
