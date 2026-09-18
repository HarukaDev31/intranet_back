<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class ContenedorProveedorEstadosProveedorHistory extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'contenedor_proveedor_estados_proveedor_history';

    protected static function organizacionRelacion(): string
    {
        return 'contenedor';
    }

    const UPDATED_AT = null;

    protected $fillable = [
        'id_proveedor',
        'id_contenedor',
        'estado',
        'source',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(CotizacionProveedor::class, 'id_proveedor');
    }

    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }
}
