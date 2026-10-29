<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $abono_id
 * @property int $solicitud_proveedor_id
 * @property float $monto
 * @property PagoProveedorAbono|null $abono
 * @property PagoProveedorSolicitudProveedor|null $proveedor
 */
class PagoProveedorAbonoAplicacion extends Model
{
    protected $table = 'pago_proveedor_abono_aplicaciones';

    protected $fillable = [
        'abono_id',
        'solicitud_proveedor_id',
        'monto',
    ];

    protected $casts = [
        'monto' => 'float',
    ];

    /**
     * @return BelongsTo<PagoProveedorAbono, $this>
     */
    public function abono(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorAbono::class, 'abono_id');
    }

    /**
     * @return BelongsTo<PagoProveedorSolicitudProveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorSolicitudProveedor::class, 'solicitud_proveedor_id');
    }
}
