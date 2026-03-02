<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;

class GuiaRemision extends Model
{
    protected $table = 'contenedor_consolidado_guias_remision';

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

