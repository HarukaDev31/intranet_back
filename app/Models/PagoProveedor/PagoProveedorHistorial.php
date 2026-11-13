<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $action
 * @property string|null $detail
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string|null $area
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class PagoProveedorHistorial extends Model
{
    public const ENTITY_QUOTE = 'cotizacion';
    public const ENTITY_REQUEST = 'solicitud';
    public const AREA_SALES = 'Ventas';
    public const AREA_ADMINISTRATION = 'Administración';
    public const AREA_SYSTEM = 'Sistema';
    public const AREA_CHINA = 'Operaciones China';

    protected $table = 'pago_proveedor_historial';

    public $timestamps = false;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'detail',
        'user_id',
        'user_name',
        'area',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
