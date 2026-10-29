<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;

class PagoProveedorConfiguracion extends Model
{
    protected $table = 'pago_proveedor_configuracion';

    protected $fillable = ['clave', 'valor'];

    protected $casts = [
        'valor' => 'array',
    ];
}
