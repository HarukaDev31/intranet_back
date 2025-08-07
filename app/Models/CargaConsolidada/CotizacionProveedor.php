<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotizacionProveedor extends Model
{
    use HasFactory;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'contenedor_consolidado_cotizacion_proveedores';
    public $timestamps = false;

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'id_cotizacion',
        'products',
        'qty_box',
        'cbm_total',
        'peso',
        'supplier',
        'code_supplier',
        'supplier_phone',
        'qty_box_china',
        'cbm_total_china',
        'arrive_date_china',
        'estado_almacen',
        'estado_china',
        'id_contenedor',
        'nota',
        'estados',
        'volumen_doc',
        'valor_doc',
        'factura_comercial',
        'excel_confirmacion',
        'send_rotulado_status',
        'packing_list',
        'estados_proveedor'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'qty_box' => 'integer',
        'qty_box_china' => 'integer',
        'arrive_date_china' => 'date'
    ];

    /**
     * Los atributos que pueden ser nulos.
     *
     * @var array
     */
    protected $nullable = [
        'qty_box',
        'cbm_total',
        'peso',
        'qty_box_china',
        'cbm_total_china',
        'arrive_date_china',
        'volumen_doc',
        'valor_doc'
    ];

    /**
     * Boot del modelo
        */
        protected static function boot()
        {
            parent::boot();

           
        }

    /**
     * Obtiene la cotización asociada a este proveedor.
     */
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    /**
     * Obtiene el contenedor asociado a este proveedor.
     */
    public function contenedor()
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }

    /**
     * Obtiene la documentación asociada a este proveedor.
     */
    public function documentacion()
    {
        return $this->hasMany(CotizacionDocumentacion::class, 'id_proveedor');
    }

    /**
     * Obtiene la documentación de almacén asociada a este proveedor.
     */
    public function documentacionAlmacen()
    {
        return $this->hasMany(AlmacenDocumentacion::class, 'id_proveedor');
    }

    /**
     * Obtiene la inspección de almacén asociada a este proveedor.
     */
    public function inspeccionAlmacen()
    {
        return $this->hasMany(AlmacenInspection::class, 'id_proveedor');
    }
} 