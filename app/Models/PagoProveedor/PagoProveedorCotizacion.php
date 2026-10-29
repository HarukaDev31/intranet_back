<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $codigo
 * @property int|null $calculadora_importacion_id
 * @property int|null $id_cotizacion_cc
 * @property string $cod_cotizacion_cc
 * @property string $estado
 * @property \Carbon\Carbon|null $vigencia_fin
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
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorCotizacionProveedor> $proveedores
 * @property PagoProveedorSolicitud|null $solicitud
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorHistorial> $historial
 */
class PagoProveedorCotizacion extends Model
{
    protected $table = 'pago_proveedor_cotizaciones';

    protected $fillable = [
        'codigo',
        'calculadora_importacion_id',
        'id_cotizacion_cc',
        'cod_cotizacion_cc',
        'estado',
        'vigencia_fin',
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
        'vigencia_fin' => 'datetime',
        'tipo_cambio' => 'float',
        'tipo_cambio_confirmado' => 'boolean',
    ];

    /**
     * @return HasMany<PagoProveedorCotizacionProveedor, $this>
     */
    public function proveedores(): HasMany
    {
        return $this->hasMany(PagoProveedorCotizacionProveedor::class, 'cotizacion_id')->orderBy('orden');
    }

    /**
     * @return HasOne<PagoProveedorSolicitud, $this>
     */
    public function solicitud(): HasOne
    {
        return $this->hasOne(PagoProveedorSolicitud::class, 'cotizacion_id');
    }

    /**
     * @return HasMany<PagoProveedorHistorial, $this>
     */
    public function historial(): HasMany
    {
        return $this->hasMany(PagoProveedorHistorial::class, 'entidad_id')
            ->where('entidad_tipo', 'cotizacion')
            ->orderByDesc('id');
    }
}
