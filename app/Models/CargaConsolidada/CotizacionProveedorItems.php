<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotizacionProveedorItems extends Model
{
    use HasFactory;

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
    ];

    protected $casts = [
        'id_contenedor' => 'integer',
        'id_cotizacion' => 'integer',
        'id_proveedor' => 'integer',
        'initial_price' => 'decimal:2',
        'initial_qty' => 'integer',
        'final_price' => 'decimal:2',
        'final_qty' => 'integer',
    ];

    public function proveedor()
    {
        return $this->belongsTo(CotizacionProveedor::class, 'id_proveedor');
    }

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    public function contenedor()
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }
}


