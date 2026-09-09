<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContenedorSeguimientoDriveCellHistory extends Model
{
    protected $table = 'contenedor_seguimiento_drive_cell_history';

    const UPDATED_AT = null;

    protected $fillable = [
        'cell_id',
        'snapshot_id',
        'id_contenedor',
        'sheet_name',
        'row_key',
        'column_key',
        'cell_ref',
        'old_value',
        'new_value',
        'change_source',
    ];

    public function cell(): BelongsTo
    {
        return $this->belongsTo(ContenedorSeguimientoDriveCell::class, 'cell_id');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ContenedorSeguimientoDriveSnapshot::class, 'snapshot_id');
    }

    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }
}
