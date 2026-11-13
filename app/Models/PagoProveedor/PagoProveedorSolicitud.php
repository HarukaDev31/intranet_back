<?php

namespace App\Models\PagoProveedor;

use App\Models\Organizacion;
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
 * @property string|null $partner
 * @property int|null $organizacion_id
 * @property string|null $country
 * @property \Illuminate\Support\Carbon|null $request_date
 * @property string|null $origin
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property PagoProveedorCotizacion|null $cotizacion
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorSolicitudProveedor> $proveedores
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorAbono> $abonos
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorIngreso> $ingresos
 * @property Organizacion|null $organizacion
 */
class PagoProveedorSolicitud extends Model
{
    public const ORIGIN_SALES = 'sales';
    public const ORIGIN_MANUAL = 'china_manual';
    public const PARTNER_OWN = 'Pro Business - PE';
    public const PARTNERS = [self::PARTNER_OWN, 'Genuino - BOG', 'Javiandy / ECU-DAV'];
    public const COUNTRY_PERU = 'Perú';
    public const COUNTRY_ECUADOR = 'Ecuador';
    public const COUNTRY_BOLIVIA = 'Bolivia';
    public const COUNTRIES = [self::COUNTRY_PERU, self::COUNTRY_ECUADOR, self::COUNTRY_BOLIVIA];

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
        'organizacion_id',
        'country',
        'request_date',
        'origin',
        'user_id',
    ];

    protected $casts = [
        'exchange_rate' => 'float',
        'exchange_rate_confirmed' => 'boolean',
        'request_date' => 'date',
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
     * @return BelongsTo<Organizacion, $this>
     */
    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id', 'ID_Organizacion');
    }

    /**
     * @return HasMany<PagoProveedorIngreso, $this>
     */
    public function ingresos(): HasMany
    {
        return $this->hasMany(PagoProveedorIngreso::class, 'request_id')->orderBy('id');
    }

    /**
     * @return HasMany<PagoProveedorAbono, $this>
     */
    public function abonos(): HasMany
    {
        return $this->hasMany(PagoProveedorAbono::class, 'request_id')->orderByDesc('id');
    }
}
