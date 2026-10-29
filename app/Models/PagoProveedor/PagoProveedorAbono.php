<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $solicitud_id
 * @property float $importe
 * @property \Illuminate\Support\Carbon|null $fecha_pago
 * @property string $numero_operacion
 * @property string $banco
 * @property bool $interbancaria
 * @property string $voucher_path
 * @property string $voucher_nombre
 * @property string $estado
 * @property float $excedente
 * @property string|null $excedente_decision
 * @property bool $operacion_repetida
 * @property int|null $usuario_id
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorAbonoAplicacion> $aplicaciones
 */
class PagoProveedorAbono extends Model
{
    protected $table = 'pago_proveedor_abonos';

    protected $fillable = [
        'solicitud_id',
        'importe',
        'fecha_pago',
        'numero_operacion',
        'banco',
        'interbancaria',
        'voucher_path',
        'voucher_nombre',
        'estado',
        'excedente',
        'excedente_decision',
        'operacion_repetida',
        'usuario_id',
    ];

    protected $casts = [
        'importe' => 'float',
        'fecha_pago' => 'date:Y-m-d',
        'interbancaria' => 'boolean',
        'excedente' => 'float',
        'operacion_repetida' => 'boolean',
    ];

    /**
     * @return BelongsTo<PagoProveedorSolicitud, $this>
     */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorSolicitud::class, 'solicitud_id');
    }

    /**
     * @return HasMany<PagoProveedorAbonoAplicacion, $this>
     */
    public function aplicaciones(): HasMany
    {
        return $this->hasMany(PagoProveedorAbonoAplicacion::class, 'abono_id');
    }
}
