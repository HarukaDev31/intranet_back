<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;

class PagoProveedorMensaje extends Model
{
    protected $table = 'pago_proveedor_mensajes';

    public $timestamps = false;

    protected $fillable = [
        'cotizacion_id',
        'solicitud_id',
        'tipo',
        'destinatario',
        'cuerpo',
        'usuario_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
