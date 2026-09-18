<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

/**
 * Linea de costo del desglose de una cotizacion "resumen" (ej. "Valor de
 * Mercaderia", "Tributos e Impuestos Aduaneros"). El concepto es texto libre
 * a proposito: cada pais/organizacion tiene sus propias categorias de costo
 * y no queremos migrar columnas cada vez que se agrega un pais nuevo.
 */
class CotizacionProveedorResumenCosto extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'cotizacion_proveedor_resumen_costo';

    protected static function organizacionRelacion(): string
    {
        return 'resumen';
    }

    protected $fillable = [
        'id_cotizacion_proveedor_resumen',
        'concepto',
        'orden',
        'valor',
    ];

    protected $casts = [
        'orden' => 'integer',
        'valor' => 'decimal:2',
    ];

    public function resumen(): BelongsTo
    {
        return $this->belongsTo(CotizacionProveedorResumen::class, 'id_cotizacion_proveedor_resumen');
    }
}
