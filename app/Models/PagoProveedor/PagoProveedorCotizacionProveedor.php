<?php

namespace App\Models\PagoProveedor;

use App\Models\CargaConsolidada\CotizacionProveedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $quote_id
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
 * @property string|null $proforma_path
 * @property string|null $proforma_name
 * @property CotizacionProveedor|null $consolidatedProvider
 */
class PagoProveedorCotizacionProveedor extends Model
{
    public const CURRENCY_USD = 'USD';
    public const CURRENCY_RMB = 'RMB';
    public const PAYMENT_TT = 'tt';
    public const PAYMENT_TRANSFER = 'transferencia';
    public const PAYMENT_CARD = 'tarjeta';
    public const FULL_SHARE = 100;
    public const MODE_TOTAL = 'total';
    public const MODE_SPLIT = 'fraccionado';

    protected $table = 'pago_proveedor_cotizacion_proveedores';

    protected $fillable = [
        'quote_id',
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
        'proforma_path',
        'proforma_name',
    ];

    protected $casts = [
        'cargo_value_usd' => 'float',
        'first_share_percent' => 'float',
        'second_share_percent' => 'float',
        'second_installment_on' => 'date:Y-m-d',
        'charges_bank_fee' => 'boolean',
        'charges_admin_fee' => 'boolean',
    ];

    /**
     * @return BelongsTo<PagoProveedorCotizacion, $this>
     */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorCotizacion::class, 'quote_id');
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
}
