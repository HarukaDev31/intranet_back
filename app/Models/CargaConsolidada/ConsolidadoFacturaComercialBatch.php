<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;

class ConsolidadoFacturaComercialBatch extends Model
{
    protected $table = 'consolidado_factura_comercial_batches';

    protected $fillable = [
        'id_contenedor',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'created_by',
        'file_path',
        'nombre_archivo',
        'mensaje_error',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
    ];
}
