<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $order_number
 * @property int $quote_id
 * @property string $consolidated_quote_code
 * @property int|null $consolidated_quote_id
 * @property float|null $exchange_rate
 * @property bool $exchange_rate_confirmed
 * @property string|null $client_name
 * @property string|null $client_document
 * @property string|null $client_whatsapp
 * @property string|null $client_email
 * @property string|null $consolidation
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property PagoProveedorCotizacion|null $cotizacion
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorSolicitudProveedor> $proveedores
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorAbono> $abonos
 */
class PagoProveedorSolicitud extends Model
{
    public const ORIGIN_SALES = 'sales';
    public const ORIGIN_MANUAL = 'china_manual';
    public const PARTNER_OWN = 'Pro Business - PE';
    public const PARTNERS = [self::PARTNER_OWN, 'Genuino - BOG', 'Javiandy / ECU-DAV'];

    public static function partnerLabel(?string $partner): string
    {
        $name = trim((string) $partner);

        return $name === '' || stripos($name, 'pro business') !== false ? self::PARTNER_OWN : $name;
    }
    protected $table = 'pago_proveedor_solicitudes';

    protected $fillable = [
        'order_number',
        'quote_id',
        'consolidated_quote_code',
        'consolidated_quote_id',
        'exchange_rate',
        'exchange_rate_confirmed',
        'client_name',
        'client_document',
        'client_whatsapp',
        'client_email',
        'consolidation',
        'partner',
        'origin',
        'user_id',
    ];

    protected $casts = [
        'exchange_rate' => 'float',
        'exchange_rate_confirmed' => 'boolean',
    ];

    /**
     * @return BelongsTo<PagoProveedorCotizacion, $this>
     */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorCotizacion::class, 'quote_id');
    }

    /**
     * @return HasMany<PagoProveedorSolicitudProveedor, $this>
     */
    public function proveedores(): HasMany
    {
        return $this->hasMany(PagoProveedorSolicitudProveedor::class, 'request_id')->orderBy('position');
    }

    /**
     * @return HasMany<PagoProveedorAbono, $this>
     */
    public function abonos(): HasMany
    {
        return $this->hasMany(PagoProveedorAbono::class, 'request_id')->orderByDesc('id');
    }
}
