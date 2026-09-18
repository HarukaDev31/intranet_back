<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class DocumentacionObservacion extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'contenedor_consolidado_documentacion_observaciones';

    protected static function organizacionRelacion(): string
    {
        return 'proveedor';
    }

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
