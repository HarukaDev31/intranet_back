<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\CotizacionProveedorItems;

class CotizacionProveedor extends Model
{
    protected $table = 'contenedor_consolidado_cotizacion_proveedores';
    
    protected $fillable = [
        'id_cotizacion',
        'id_contenedor',
        'code_supplier',
        'products',
        'estado',
        'estados',
        'volumen_doc',
        'valor_doc',
        'factura_comercial',
        'excel_confirmacion',
        'packing_list',
        'qty_box_china',
        'arrive_date_china',
        'arrive_date',
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
        'send_rotulado_status',
        'tipo_rotulado',
        'invoice_status',
        'packing_status',
        'excel_conf_status'
    ];

    // Permitir asignación masiva de los nuevos estados de documentos (casts definidos abajo)

    // Agregar nuevos campos de estado de documentos
    protected $casts = [
        'invoice_status' => 'string',
        'packing_status' => 'string',
        'excel_conf_status' => 'string'
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

    /**
     * Relación con AlmacenInspection
     */
    public function inspectionAlmacen()
    {
        return $this->hasMany(AlmacenInspection::class, 'id_proveedor');
    }
    //relation items with contenedor_consolidado_cotizacion_proveedores_items
    public function items()
    {
        return $this->hasMany(CotizacionProveedorItems::class, 'id_proveedor');
    }
}