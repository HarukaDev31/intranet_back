<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TramiteAduanaPago extends Model
{
    protected $table = 'tramite_aduana_pagos';

    protected $fillable = [
        'id_tramite',
        'id_tipo_permiso',
        'id_documento',
        'monto',
        'fecha_pago',
        'observacion',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_pago' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(ConsolidadoCotizacionAduanaTramite::class, 'id_tramite');
    }

    public function tipoPermiso(): BelongsTo
    {
        return $this->belongsTo(TramiteAduanaTipoPermiso::class, 'id_tipo_permiso');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(TramiteAduanaDocumento::class, 'id_documento');
    }
}
