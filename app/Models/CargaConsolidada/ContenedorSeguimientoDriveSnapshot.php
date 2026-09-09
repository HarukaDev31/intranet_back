<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContenedorSeguimientoDriveSnapshot extends Model
{
    protected $table = 'contenedor_seguimiento_drive_snapshots';

    const UPDATED_AT = null;

    protected $fillable = [
        'id_contenedor',
        'drive_file_id',
        'file_name',
        'trigger',
        'cells_upserted',
        'cells_history',
        'status',
        'error',
    ];

    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ContenedorSeguimientoDriveCellHistory::class, 'snapshot_id');
    }
}
