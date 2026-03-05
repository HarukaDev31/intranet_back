<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento asociado a una cotización de la calculadora de importación.
 * Tabla: calculadora_importacion_documentos
 */
class CalculadoraImportacionDocumento extends Model
{
    protected $table = 'calculadora_importacion_documentos';

    protected $fillable = [
        'id_calculadora_importacion',
        'file_url',
        'file_name',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function calculadoraImportacion(): BelongsTo
    {
        return $this->belongsTo(CalculadoraImportacion::class, 'id_calculadora_importacion');
    }
}
