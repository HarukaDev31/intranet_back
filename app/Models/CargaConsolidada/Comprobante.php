<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;

class Comprobante extends Model
{
    protected $table = 'contenedor_consolidado_comprobantes';

    protected $fillable = [
        'quotation_id',
        'tipo_comprobante',
        'valor_comprobante',
        'tiene_detraccion',
        'monto_detraccion_dolares',
        'monto_detraccion_soles',
        'file_name',
        'file_path',
        'size',
        'mime_type',
        'extracted_by_ai',
    ];

    protected $casts = [
        'valor_comprobante'        => 'float',
        'tiene_detraccion'         => 'boolean',
        'monto_detraccion_dolares' => 'float',
        'monto_detraccion_soles'   => 'float',
        'size'                     => 'integer',
        'extracted_by_ai'          => 'boolean',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'quotation_id');
    }

    public function constancia()
    {
        return $this->hasOne(Detraccion::class, 'comprobante_id');
    }
}
