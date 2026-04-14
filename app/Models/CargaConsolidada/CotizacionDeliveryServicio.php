<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;

class CotizacionDeliveryServicio extends Model
{
    public $timestamps = false;

    protected $table = 'contenedor_consolidado_cotizacion_delivery_servicio';

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
