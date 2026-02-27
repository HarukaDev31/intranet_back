<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;

class ComprobanteForm extends Model
{
    protected $table = 'consolidado_comprobante_forms';

    protected $fillable = [
        'id_contenedor',
        'id_user',
        'id_cotizacion',
        'tipo_comprobante',
        'destino_entrega',
        'razon_social',
        'ruc',
        'nombre_completo',
        'dni_carnet',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }
}
