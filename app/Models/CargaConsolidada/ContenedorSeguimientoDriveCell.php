<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContenedorSeguimientoDriveCell extends Model
{
    protected $table = 'contenedor_seguimiento_drive_cells';

    protected $fillable = [
        'id_contenedor',
        'sheet_name',
        'row_key',
        'column_key',
        'id_cotizacion',
        'id_proveedor',
        'cell_ref',
        'row_number',
        'column_letter',
        'cell_value',
        'is_manual',
        'last_seen_at',
    ];

    protected $casts = [
        'is_manual' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(CotizacionProveedor::class, 'id_proveedor');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ContenedorSeguimientoDriveCellHistory::class, 'cell_id');
    }
}
