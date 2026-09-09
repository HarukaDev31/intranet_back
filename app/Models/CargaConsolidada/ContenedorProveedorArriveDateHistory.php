<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContenedorProveedorArriveDateHistory extends Model
{
    protected $table = 'contenedor_proveedor_arrive_date_history';

    const UPDATED_AT = null;

    protected $fillable = [
        'id_proveedor',
        'id_contenedor',
        'field',
        'value',
        'source',
    ];

    protected $casts = [
        'value' => 'date',
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
