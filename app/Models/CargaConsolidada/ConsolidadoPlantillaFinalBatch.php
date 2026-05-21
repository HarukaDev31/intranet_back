<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;

class ConsolidadoPlantillaFinalBatch extends Model
{
    protected $table = 'consolidado_plantilla_final_batches';

    protected $fillable = [
        'id_contenedor',
        'clientes_excel',
        'clientes_completados',
        'clientes_error',
        'detalle_json',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'created_by',
        'plantilla_url',
        'zip_path',
        'nombre_plantilla',
        'mensaje_error',
    ];

    protected $casts = [
        'detalle_json' => 'array',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
    ];
}
