<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class ContenedorProveedorEstadosTrackingEstado extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'contenedor_proveedor_estados_tracking_estados';

    protected static function organizacionRelacion(): string
    {
        return 'proveedor';
    }

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
