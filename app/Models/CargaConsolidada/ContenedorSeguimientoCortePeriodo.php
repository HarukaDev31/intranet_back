<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class ContenedorSeguimientoCortePeriodo extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'contenedor_seguimiento_corte_periodos';

    protected static function organizacionRelacion(): string
    {
        return 'contenedor';
    }

    const UPDATED_AT = null;

    protected $fillable = [
        'id_contenedor',
        'periodo_inicio',
        'periodo_fin',
    ];

    protected $casts = [
        'periodo_inicio' => 'datetime',
        'periodo_fin' => 'datetime',
    ];

    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(ContenedorSeguimientoCorteCliente::class, 'id_corte');
    }
}
