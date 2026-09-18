<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class GuiaRemision extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'contenedor_consolidado_guias_remision';

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

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'quotation_id');
    }
}

