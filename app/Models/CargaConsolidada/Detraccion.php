<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class Detraccion extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'contenedor_consolidado_detracciones';

    protected static function organizacionRelacion(): string
    {
        return 'cotizacion';
    }

    protected $fillable = [
        'quotation_id',
        'comprobante_id',
        'monto_detraccion',
        'file_name',
        'file_path',
        'size',
        'mime_type',
        'extracted_by_ai',
    ];

    protected $casts = [
        'monto_detraccion' => 'float',
        'size'             => 'integer',
        'extracted_by_ai'  => 'boolean',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'quotation_id');
    }

    public function comprobante()
    {
        return $this->belongsTo(Comprobante::class, 'comprobante_id');
    }
}
