<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class ContenedorSeguimientoRowSync extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'contenedor_seguimiento_row_sync';

    protected static function organizacionRelacion(): string
    {
        return 'contenedor';
    }

    protected $fillable = [
        'id_contenedor',
        'tabla',
        'id_cotizacion',
        'id_proveedor',
        'data_hash',
        'ultima_actualizacion',
    ];

    protected $casts = [
        'ultima_actualizacion' => 'datetime',
    ];

    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(CotizacionProveedor::class, 'id_proveedor');
    }
}
