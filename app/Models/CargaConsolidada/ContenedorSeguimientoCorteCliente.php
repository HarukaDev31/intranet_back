<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class ContenedorSeguimientoCorteCliente extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'contenedor_seguimiento_corte_clientes';

    protected static function organizacionRelacion(): string
    {
        return 'proveedor';
    }

    const UPDATED_AT = null;

    protected $fillable = [
        'id_corte',
        'id_proveedor',
        'id_cotizacion',
        'nombre_cliente',
        'code_supplier',
        'products',
        'fecha_cambio',
    ];

    protected $casts = [
        'fecha_cambio' => 'datetime',
    ];

    public function corte(): BelongsTo
    {
        return $this->belongsTo(ContenedorSeguimientoCortePeriodo::class, 'id_corte');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(CotizacionProveedor::class, 'id_proveedor');
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }
}
