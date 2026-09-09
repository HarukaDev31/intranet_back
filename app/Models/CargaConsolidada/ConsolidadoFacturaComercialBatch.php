<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class ConsolidadoFacturaComercialBatch extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'consolidado_factura_comercial_batches';

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
