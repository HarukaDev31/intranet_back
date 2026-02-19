<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoPermisoDerechoTramite extends Model
{
    protected $table = 'pagos_permiso_derecho_tramite';

    protected $fillable = [
        'id_tramite',
        'id_tipo_permiso',
        'ruta',
        'nombre_original',
        'extension',
        'peso',
        'monto',
        'banco',
        'fecha_cierre',
    ];

    protected $casts = [
        'peso' => 'integer',
        'monto' => 'decimal:2',
        'fecha_cierre' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->ruta);
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(ConsolidadoCotizacionAduanaTramite::class, 'id_tramite');
    }

    public function tipoPermiso(): BelongsTo
    {
        return $this->belongsTo(TramiteAduanaTipoPermiso::class, 'id_tipo_permiso');
    }
}
