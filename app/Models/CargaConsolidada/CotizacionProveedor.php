<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\CotizacionProveedorItems;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

/**
 * @property int $id
 * @property int|null $id_cotizacion
 * @property int|null $id_contenedor
 * @property int|null $id_contenedor_pago
 * @property int|null $id_proveedor
 * @property int|null $organizacion_id
 * @property string|null $code_supplier
 * @property string|null $products
 * @property string|null $supplier
 * @property string|null $supplier_phone
 * @property string|null $estado
 * @property string|null $estados
 * @property string|null $estados_proveedor
 * @property string|null $estado_china
 * @property string|null $tipo_rotulado
 * @property string|null $modo_cotizacion
 * @property string|float|int|null $volumen_doc
 * @property string|float|int|null $valor_doc
 * @property string|float|int|null $peso
 * @property string|float|int|null $peso_china
 * @property string|float|int|null $cbm_total
 * @property string|float|int|null $cbm_total_china
 * @property string|float|int|null $cbm_imo
 * @property string|float|int|null $maxcbm
 * @property string|float|int|null $qty_box
 * @property string|float|int|null $qty_box_china
 * @property string|float|int|null $qty_pallet_china
 * @property string|null $factura_comercial
 * @property string|null $excel_confirmacion
 * @property string|null $excel_confirmacion_drive_link
 * @property string|null $packing_list
 * @property-read Cotizacion|null $cotizacion
 * @property-read Contenedor|null $contenedor
 */
class CotizacionProveedor extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'contenedor_consolidado_cotizacion_proveedores';

    protected static function organizacionRelacion(): string
    {
        return 'contenedor';
    }


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
        'excel_confirmacion_drive_link',
        'packing_list',
        'qty_box_china',
        'qty_pallet_china',
        'arrive_date_china',
        'arrive_date',
        'cbm_total_china',
        'estados_proveedor',
        'peso',
        'peso_china',
        'qty_box',
        'cbm_total',
        'cbm_imo',
        'maxcbm',
        'supplier',
        'supplier_phone',
        'id_proveedor',
        'id_contenedor_pago',
        'organizacion_id',
        'estado_china',
        'send_rotulado_status',
        'tipo_rotulado',
        'invoice_status',
        'packing_status',
        'excel_conf_status',
        'invoice_status_final',
        'packing_status_final',
        'excel_conf_status_final',
        'excel_conf_form_cerrado',
        'canal',
        'fecha_entrega',
        'observaciones_seguimiento',
        'modo_cotizacion'
    ];

    // Permitir asignación masiva de los nuevos estados de documentos (casts definidos abajo)

    // Agregar nuevos campos de estado de documentos
    protected $casts = [
        'maxcbm' => 'decimal:10',
        'invoice_status' => 'string',
        'packing_status' => 'string',
        'excel_conf_status' => 'string',
        'invoice_status_final' => 'string',
        'packing_status_final' => 'string',
        'excel_conf_status_final' => 'string',
        'excel_conf_form_cerrado' => 'boolean',
        'canal' => 'string',
        'fecha_entrega' => 'date:Y-m-d'
    ];

    protected $attributes = [
        'tipo_rotulado' => 'pendiente',
    ];

    /**
     * Relación con Cotizacion
     *
     * @return BelongsTo<Cotizacion, $this>
     */
    public function cotizacion(): BelongsTo
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

    /**
     * Cabecera "resumen" (sin items) cuando modo_cotizacion = 'resumen'.
     */
    public function resumen(): HasOne
    {
        return $this->hasOne(CotizacionProveedorResumen::class, 'id_proveedor');
    }

    /**
     * Historial de archivos subidos + lo que la IA extrajo de cada uno.
     */
    public function archivosIa()
    {
        return $this->hasMany(CotizacionProveedorArchivoIa::class, 'id_proveedor');
    }

    /**
     * Documentos del perfil cotizador para este proveedor (hasta 4).
     */
    public function documentosCotizador()
    {
        return $this->hasMany(CotizacionCotizadorProveedorDocumento::class, 'id_proveedor');
    }
}