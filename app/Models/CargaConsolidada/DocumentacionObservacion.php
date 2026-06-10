<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;

class DocumentacionObservacion extends Model
{
    protected $table = 'contenedor_consolidado_documentacion_observaciones';

    protected $fillable = [
        'id_proveedor',
        'categoria',
        'mensaje',
        'user_id',
        'user_name',
    ];

    public function proveedor()
    {
        return $this->belongsTo(CotizacionProveedor::class, 'id_proveedor', 'id');
    }
}
