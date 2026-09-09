<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class BoletinQuimicoCotizacionItem extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'boletin_quimico_cotizacion_item';

    protected static function organizacionRelacion(): string
    {
        return 'contenedor';
    }

    protected $fillable = [
        'id_contenedor',
        'id_cotizacion',
        'id_cotizacion_proveedor_item',
        'monto_boletin',
        'estado',
    ];

    protected $casts = [
        'monto_boletin' => 'decimal:4',
    ];

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_ADELANTO_PAGADO = 'adelanto_pagado';
    public const ESTADO_PAGADO = 'pagado';

    public function contenedor()
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    public function cotizacionProveedorItem()
    {
        return $this->belongsTo(CotizacionProveedorItem::class, 'id_cotizacion_proveedor_item');
    }

    public function pagos()
    {
        return $this->hasMany(PagoBoletinQuimico::class, 'id_boletin_quimico_item');
    }
}
