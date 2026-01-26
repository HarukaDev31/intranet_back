<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaComercial extends Model
{
    protected $table = 'contenedor_consolidado_facturas_e';

    protected $fillable = [
        'quotation_id',
        'file_name',
        'file_path',
        'size',
        'mime_type',
    ];

    /**
     * Relación con la cotización
     */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'quotation_id', 'id');
    }
}

