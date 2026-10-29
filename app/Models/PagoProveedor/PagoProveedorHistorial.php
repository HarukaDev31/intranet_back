<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $entidad_tipo
 * @property int $entidad_id
 * @property string $accion
 * @property string|null $detalle
 * @property int|null $usuario_id
 * @property string|null $usuario_nombre
 * @property string|null $area
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class PagoProveedorHistorial extends Model
{
    protected $table = 'pago_proveedor_historial';

    public $timestamps = false;

    protected $fillable = [
        'entidad_tipo',
        'entidad_id',
        'accion',
        'detalle',
        'usuario_id',
        'usuario_nombre',
        'area',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
