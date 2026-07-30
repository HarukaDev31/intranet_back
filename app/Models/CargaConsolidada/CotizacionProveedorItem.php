<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;

class CotizacionProveedorItem extends Model
{
    protected $table = 'contenedor_consolidado_cotizacion_proveedores_items';

    protected $fillable = [
        'id_contenedor',
        'id_cotizacion',
        'id_proveedor',
        'initial_price',
        'initial_qty',
        'initial_name',
        'final_price',
        'final_qty',
        'final_name',
        'tipo_producto',
        'caracteristicas',
        'confirmacion_qty',
        'confirmacion_precio',
    ];

    protected \ = [
        'caracteristicas' => 'array',
        'confirmacion_qty' => 'decimal:2',
        'confirmacion_precio' => 'decimal:2',
    ];
}


