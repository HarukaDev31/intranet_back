<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CotizacionProveedor extends Model
{
    protected $table = 'contenedor_consolidado_cotizacion_proveedores';
    protected $primaryKey = 'id';
    public $timestamps = false;

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

    protected $casts = [
        'qty_box' => 'integer',
        'cbm_total' => 'decimal:2',
        'peso' => 'decimal:2',
        'qty_box_china' => 'integer',
        'cbm_total_china' => 'decimal:2',
        'arrive_date_china' => 'date',
        'volumen_doc' => 'decimal:2',
        'valor_doc' => 'decimal:2'
    ];

    // Enums disponibles
    const ESTADOS_ALMACEN = ['PENDIENTE', 'INSPECTION', 'LOADED', 'NO LOADED'];
    const ESTADOS_CHINA = ['PENDIENTE', 'INSPECTION', 'LOADED', 'NO LOADED'];
    const ESTADOS = ['ROTULADO', 'DATOS PROVEEDOR', 'COBRANDO', 'INSPECCIONADO', 'RESERVADO', 'EMBARCADO', 'NO EMBARCADO'];
    const ESTADOS_PROVEEDOR = ['NC', 'C', 'R', 'CONTACTED', 'NS', 'INSPECTION', 'LOADED', 'NO LOADED'];
    const SEND_ROTULADO_STATUS = ['PENDING', 'SENDED'];

    /**
     * Relación con Cotizacion
     */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion', 'id');
    }

    /**
     * Relación con Contenedor
     */
    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor', 'id');
    }

    /**
     * Scope para filtrar por estado de proveedor
     */
    public function scopePorEstadoProveedor($query, $estado)
    {
        if ($estado && $estado !== '0') {
            return $query->where('estados_proveedor', $estado);
        }
        return $query;
    }

    /**
     * Scope para filtrar por estado
     */
    public function scopePorEstado($query, $estado)
    {
        if ($estado && $estado !== '0') {
            return $query->where('estados', $estado);
        }
        return $query;
    }

    /**
     * Scope para filtrar por cotización
     */
    public function scopePorCotizacion($query, $idCotizacion)
    {
        return $query->where('id_cotizacion', $idCotizacion);
    }

    /**
     * Scope para filtrar por contenedor
     */
    public function scopePorContenedor($query, $idContenedor)
    {
        return $query->where('id_contenedor', $idContenedor);
    }

    /**
     * Obtener opciones de filtro disponibles
     */
    public static function getOpcionesFiltro()
    {
        return [
            'estados_almacen' => self::ESTADOS_ALMACEN,
            'estados_china' => self::ESTADOS_CHINA,
            'estados' => self::ESTADOS,
            'estados_proveedor' => self::ESTADOS_PROVEEDOR,
            'send_rotulado_status' => self::SEND_ROTULADO_STATUS
        ];
    }
} 