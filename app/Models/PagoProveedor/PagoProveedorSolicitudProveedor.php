<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $solicitud_id
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
 * @property float $bancaria_monto
 * @property float $administrativa_monto
 * @property float $total_monto
 * @property int|null $bancaria_incluida_en_orden
 * @property array<string, mixed>|null $resumen_json
 * @property string|null $proforma_path
 * @property string|null $proforma_nombre
 * @property string|null $codigo_solicitud
 * @property int $etapa
 * @property string $estado
 * @property int $cuota_actual
 * @property int $pago_actual
 * @property PagoProveedorSolicitud|null $solicitud
 * @property PagoProveedorDatoProveedor|null $datos
 * @property \Illuminate\Database\Eloquent\Collection<int, PagoProveedorAbonoAplicacion> $aplicaciones
 */
class PagoProveedorSolicitudProveedor extends Model
{
    protected $table = 'pago_proveedor_solicitud_proveedores';

    protected $fillable = [
        'solicitud_id',
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
        'bancaria_monto',
        'administrativa_monto',
        'total_monto',
        'bancaria_incluida_en_orden',
        'resumen_json',
        'proforma_path',
        'proforma_nombre',
        'codigo_solicitud',
        'etapa',
        'estado',
        'cuota_actual',
        'pago_actual',
    ];

    protected $casts = [
        'valor_carga_usd' => 'float',
        'porcentaje_1' => 'float',
        'porcentaje_2' => 'float',
        'fecha_cuota_2' => 'date:Y-m-d',
        'comision_bancaria' => 'boolean',
        'comision_administrativa' => 'boolean',
        'bancaria_monto' => 'float',
        'administrativa_monto' => 'float',
        'total_monto' => 'float',
        'resumen_json' => 'array',
    ];

    /**
     * @return BelongsTo<PagoProveedorSolicitud, $this>
     */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(PagoProveedorSolicitud::class, 'solicitud_id');
    }

    /**
     * @return HasOne<PagoProveedorDatoProveedor, $this>
     */
    public function datos(): HasOne
    {
        return $this->hasOne(PagoProveedorDatoProveedor::class, 'solicitud_proveedor_id');
    }

    /**
     * @return HasMany<PagoProveedorAbonoAplicacion, $this>
     */
    public function aplicaciones(): HasMany
    {
        return $this->hasMany(PagoProveedorAbonoAplicacion::class, 'solicitud_proveedor_id');
    }
}
