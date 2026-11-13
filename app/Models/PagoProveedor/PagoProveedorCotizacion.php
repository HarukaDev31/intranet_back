<?php

namespace App\Models\PagoProveedor;

use App\Services\PagoProveedor\PagoProveedorVigenciaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $code
 * @property int|null $calculadora_importacion_id
 * @property int|null $consolidated_quote_id
 * @property string $consolidated_quote_code
 * @property string $status
 * @property \Carbon\Carbon|null $valid_until
 * @property float|null $exchange_rate
 * @property bool $exchange_rate_confirmed
 * @property \Illuminate\Support\Carbon|null $exchange_rate_confirmed_at
 * @property string|null $client_name
 * @property string|null $client_document
 * @property string|null $client_whatsapp
 * @property string|null $client_email
 * @property string|null $consolidation
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorCotizacionProveedor> $proveedores
 * @property PagoProveedorSolicitud|null $solicitud
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorHistorial> $historial
 */
class PagoProveedorCotizacion extends Model
{
    public const STATUS_PENDING = 'pendiente';
    public const STATUS_CONFIRMED = 'confirmada';
    public const FILTER_EXPIRED = 'vencida';

    protected $table = 'pago_proveedor_cotizaciones';

    protected $fillable = [
        'code',
        'calculadora_importacion_id',
        'consolidated_quote_id',
        'consolidated_quote_code',
        'status',
        'valid_until',
        'exchange_rate',
        'exchange_rate_confirmed',
        'exchange_rate_confirmed_at',
        'client_name',
        'client_document',
        'client_whatsapp',
        'client_email',
        'consolidation',
        'user_id',
    ];

    protected $casts = [
        'valid_until' => 'datetime',
        'exchange_rate' => 'float',
        'exchange_rate_confirmed' => 'boolean',
        'exchange_rate_confirmed_at' => 'datetime',
    ];

    /**
     * @return HasMany<PagoProveedorCotizacionProveedor, $this>
     */
    public function proveedores(): HasMany
    {
        return $this->hasMany(PagoProveedorCotizacionProveedor::class, 'quote_id')->orderBy('position');
    }

    /**
     * @return HasOne<PagoProveedorSolicitud, $this>
     */
    public function solicitud(): HasOne
    {
        return $this->hasOne(PagoProveedorSolicitud::class, 'quote_id');
    }

    /**
     * @return HasMany<PagoProveedorHistorial, $this>
     */
    public function historial(): HasMany
    {
        return $this->hasMany(PagoProveedorHistorial::class, 'entity_id')
            ->where('entity_type', PagoProveedorHistorial::ENTITY_QUOTE)
            ->orderByDesc('id');
    }

    /**
     * El tipo de cambio vale para crear la solicitud solo si es del día (hora de Lima): la cotización se
     * creó hoy o Ventas lo confirmó hoy. Una confirmación de otro día ya no cuenta (#1656).
     */
    public function exchangeRateConfirmedToday(): bool
    {
        if ((float) $this->exchange_rate <= 0) {
            return false;
        }
        $hoy = now(PagoProveedorVigenciaService::ZONA)->toDateString();
        if ($this->exchange_rate_confirmed && $this->exchange_rate_confirmed_at
            && $this->exchange_rate_confirmed_at->copy()->timezone(PagoProveedorVigenciaService::ZONA)->toDateString() === $hoy) {
            return true;
        }

        return $this->created_at !== null
            && $this->created_at->copy()->timezone(PagoProveedorVigenciaService::ZONA)->toDateString() === $hoy;
    }
}
