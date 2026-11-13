<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;

class PagoProveedorMensaje extends Model
{
    protected $table = 'pago_proveedor_mensajes';

    public $timestamps = false;

    protected $fillable = [
        'quote_id',
        'request_id',
        'message_type',
        'recipient',
        'body',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
