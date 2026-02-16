<?php

namespace App\Models\CargaConsolidada;

use App\Models\BaseDatos\BaseMediaModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TramiteAduanaDocumento extends BaseMediaModel
{
    protected $table = 'tramite_aduana_documentos';

    protected $fillable = [
        'id_tramite',
        'id_categoria',
        'nombre_documento',
        'extension',
        'peso',
        'nombre_original',
        'ruta',
    ];

    protected $casts = [
        'peso' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(ConsolidadoCotizacionAduanaTramite::class, 'id_tramite');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(TramiteAduanaCategoria::class, 'id_categoria');
    }
}
