<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $numero_orden
 * @property int $cotizacion_id
 * @property string $cod_cotizacion_cc
 * @property int|null $id_cotizacion_cc
 * @property float|null $tipo_cambio
 * @property bool $tipo_cambio_confirmado
 * @property string|null $cliente_nombre
 * @property string|null $cliente_documento
 * @property string|null $cliente_whatsapp
 * @property string|null $cliente_correo
 * @property string|null $consolidado
 * @property int|null $usuario_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property PagoProveedorCotizacion|null $cotizacion
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorSolicitudProveedor> $proveedores
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorAbono> $abonos
 */
class PagoProveedorSolicitud extends Model
{
    protected $table = 'pago_proveedor_solicitudes';

    protected $fillable = [
        'numero_orden',
        'cotizacion_id',
        'cod_cotizacion_cc',
        'id_cotizacion_cc',
        'tipo_cambio',
        'tipo_cambio_confirmado',
        'cliente_nombre',
        'cliente_documento',
        'cliente_whatsapp',
        'cliente_correo',
        'consolidado',
        'usuario_id',
    ];

    protected $casts = [
        'tipo_cambio' => 'float',
        'tipo_cambio_confirmado' => 'boolean',
    ];

    /**
     * @return BelongsTo<PagoProveedorCotizacion, $this>
     */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorCotizacion::class, 'cotizacion_id');
    }

    /**
     * @return HasMany<PagoProveedorSolicitudProveedor, $this>
     */
    public function proveedores(): HasMany
    {
        return $this->hasMany(PagoProveedorSolicitudProveedor::class, 'solicitud_id')->orderBy('orden');
    }

    /**
     * @return HasMany<PagoProveedorAbono, $this>
     */
    public function abonos(): HasMany
    {
        return $this->hasMany(PagoProveedorAbono::class, 'solicitud_id')->orderByDesc('id');
    }
}
