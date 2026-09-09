<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContenedorProveedorEstadosTrackingEstado extends Model
{
    protected $table = 'contenedor_proveedor_estados_tracking_estados';

    protected $fillable = [
        'id_proveedor',
        'id_cotizacion',
        'estado',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(CotizacionProveedor::class, 'id_proveedor');
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }
}
