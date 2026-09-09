<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class CotizacionProveedorItem extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'contenedor_consolidado_cotizacion_proveedores_items';

    protected static function organizacionRelacion(): string
    {
        return 'contenedor';
    }

    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }

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

    protected $casts = [
        'caracteristicas' => 'array',
        'confirmacion_qty' => 'decimal:2',
        'confirmacion_precio' => 'decimal:2',
    ];
}


