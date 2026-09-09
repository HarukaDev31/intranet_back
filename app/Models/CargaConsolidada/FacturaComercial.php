<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class FacturaComercial extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'contenedor_consolidado_facturas_e';

    protected static function organizacionRelacion(): string
    {
        return 'cotizacion';
    }

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

