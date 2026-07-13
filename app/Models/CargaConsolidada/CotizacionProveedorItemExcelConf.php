<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionProveedorItemExcelConf extends Model
{
    protected $table = 'contenedor_consolidado_cotizacion_proveedores_items_excel_conf';

    protected $fillable = [
        'id_cotizacion',
        'id_proveedor',
        'initial_name',
        'tipo_producto',
        'caracteristicas',
        'confirmacion_qty',
        'confirmacion_precio',
    ];

    protected $casts = [
        'id_cotizacion' => 'integer',
        'id_proveedor' => 'integer',
        'caracteristicas' => 'array',
        'confirmacion_qty' => 'decimal:2',
        'confirmacion_precio' => 'decimal:2',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(CotizacionProveedor::class, 'id_proveedor');
    }
}
