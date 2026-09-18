<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class CotizacionDeliveryServicio extends Model
{
    use SincronizaOrganizacionId;

    public $timestamps = false;

    protected $table = 'contenedor_consolidado_cotizacion_delivery_servicio';

    protected static function organizacionRelacion(): string
    {
        return 'cotizacion';
    }

    protected $fillable = [
        'id_cotizacion',
        'tipo_servicio',
        'importe',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion', 'id');
    }
}
