<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $solicitud_proveedor_id
 * @property string|null $vendedor_nombre
 * @property string|null $wechat
 * @property string $estado
 * @property string|null $origen
 * @property string $token
 * @property bool $correccion_solicitada
 * @property string|null $beneficiario_nombre
 * @property string|null $beneficiario_cuenta
 * @property string|null $beneficiario_direccion
 * @property string|null $beneficiario_pais
 * @property string|null $beneficiario_ciudad
 * @property string|null $banco_tipo_codigo
 * @property string|null $banco_codigo
 * @property string|null $banco_nombre
 * @property string|null $banco_direccion
 * @property string|null $banco_pais
 * @property string|null $banco_ciudad
 * @property bool $usa_intermediario
 * @property string|null $inter_tipo_codigo
 * @property string|null $inter_codigo
 * @property string|null $inter_nombre
 * @property string|null $inter_direccion
 * @property string|null $inter_pais
 * @property string|null $inter_ciudad
 * @property \Illuminate\Support\Carbon|null $autorizado_at
 * @property PagoProveedorSolicitudProveedor|null $proveedor
 */
class PagoProveedorDatoProveedor extends Model
{
    protected $table = 'pago_proveedor_datos_proveedor';

    protected $fillable = [
        'solicitud_proveedor_id',
        'vendedor_nombre',
        'wechat',
        'estado',
        'origen',
        'token',
        'correccion_solicitada',
        'beneficiario_nombre',
        'beneficiario_cuenta',
        'beneficiario_direccion',
        'beneficiario_pais',
        'beneficiario_ciudad',
        'banco_tipo_codigo',
        'banco_codigo',
        'banco_nombre',
        'banco_direccion',
        'banco_pais',
        'banco_ciudad',
        'usa_intermediario',
        'inter_tipo_codigo',
        'inter_codigo',
        'inter_nombre',
        'inter_direccion',
        'inter_pais',
        'inter_ciudad',
        'autorizado_at',
    ];

    protected $casts = [
        'usa_intermediario' => 'boolean',
        'correccion_solicitada' => 'boolean',
        'autorizado_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<PagoProveedorSolicitudProveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorSolicitudProveedor::class, 'solicitud_proveedor_id');
    }
}
