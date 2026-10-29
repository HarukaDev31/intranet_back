<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cotizacion_id
 * @property int $orden
 * @property string|null $code_supplier
 * @property int|null $id_proveedor_cc
 * @property string|null $nombre_proveedor
 * @property string|null $producto
 * @property string $moneda
 * @property string $tipo_pago
 * @property float $valor_carga_usd
 * @property string $modalidad_abono
 * @property string $modalidad_pago
 * @property float $porcentaje_1
 * @property float $porcentaje_2
 * @property \Illuminate\Support\Carbon|null $fecha_cuota_2
 * @property bool $comision_bancaria
 * @property bool $comision_administrativa
 * @property string|null $proforma_path
 * @property string|null $proforma_nombre
 */
class PagoProveedorCotizacionProveedor extends Model
{
    protected $table = 'pago_proveedor_cotizacion_proveedores';

    protected $fillable = [
        'cotizacion_id',
        'orden',
        'code_supplier',
        'id_proveedor_cc',
        'nombre_proveedor',
        'producto',
        'moneda',
        'tipo_pago',
        'valor_carga_usd',
        'modalidad_abono',
        'modalidad_pago',
        'porcentaje_1',
        'porcentaje_2',
        'fecha_cuota_2',
        'comision_bancaria',
        'comision_administrativa',
        'proforma_path',
        'proforma_nombre',
    ];

    protected $casts = [
        'valor_carga_usd' => 'float',
        'porcentaje_1' => 'float',
        'porcentaje_2' => 'float',
        'fecha_cuota_2' => 'date:Y-m-d',
        'comision_bancaria' => 'boolean',
        'comision_administrativa' => 'boolean',
    ];

    /**
     * @return BelongsTo<PagoProveedorCotizacion, $this>
     */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorCotizacion::class, 'cotizacion_id');
    }
}
