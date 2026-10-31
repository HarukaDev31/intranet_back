<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $setting_key
 * @property array<string, mixed>|array<int, mixed>|scalar $setting_value
 */
class PagoProveedorConfiguracion extends Model
{
    protected $table = 'pago_proveedor_configuracion';

    protected $fillable = ['setting_key', 'setting_value'];

    protected $casts = [
        'setting_value' => 'array',
    ];
}
