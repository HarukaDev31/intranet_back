<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use App\Models\CargaConsolidada\Contenedor;

class CotizacionProveedor extends Model
{
    protected $table = 'contenedor_consolidado_cotizacion_proveedores';
    
    protected $fillable = [
        'id_cotizacion',
        'id_contenedor',
        'code_supplier',
        'products',
        'estado',
        'volumen_doc',
        'valor_doc',
        'factura_comercial',
        'excel_confirmacion',
        'packing_list',
        'qty_box_china',
        'arrive_date_china',
        'cbm_total_china',
        'estados_proveedor',
        'peso',
        'qty_box',
        'cbm_total',
        'supplier',
        'supplier_phone',
        'id_proveedor',
        'id_contenedor_pago',
        'estado_china',
        'send_rotulado_status'
    ];

    /**
     * Relación con Cotizacion
     */
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion', 'id');
    }

    /**
     * Relación con Contenedor
     */
    public function contenedor()
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor', 'id');
    }
}