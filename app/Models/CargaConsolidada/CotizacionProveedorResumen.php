<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

/**
 * Cabecera de cotizacion "resumen" (sin items): usada por organizaciones cuyo
 * flujo de cotizacion viene de un documento (PDF/Excel) leido por IA, en vez
 * de la calculadora item por item. Vive junto a CotizacionProveedor cuando
 * modo_cotizacion = 'resumen'.
 */
class CotizacionProveedorResumen extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'cotizacion_proveedor_resumen';

    protected static function organizacionRelacion(): string
    {
        return 'contenedor';
    }

    protected $fillable = [
        'id_contenedor',
        'id_cotizacion',
        'id_proveedor',
        'producto',
        'volumen_cbm',
        'unidades',
        'incoterm',
        'costo_unitario_estimado',
        'inversion_total',
        'moneda',
    ];

    protected $casts = [
        'volumen_cbm' => 'decimal:4',
        'unidades' => 'integer',
        'costo_unitario_estimado' => 'decimal:4',
        'inversion_total' => 'decimal:2',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(CotizacionProveedor::class, 'id_proveedor');
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }

    public function costos(): HasMany
    {
        return $this->hasMany(CotizacionProveedorResumenCosto::class, 'id_cotizacion_proveedor_resumen')
            ->orderBy('orden');
    }
}
