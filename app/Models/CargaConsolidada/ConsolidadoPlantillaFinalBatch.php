<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class ConsolidadoPlantillaFinalBatch extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'consolidado_plantilla_final_batches';

    protected static function organizacionRelacion(): string
    {
        return 'contenedor';
    }

    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }

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
