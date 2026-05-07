<?php

namespace App\Models;

use App\Models\Concerns\NormalizesMontosDosDecimales;
use Illuminate\Database\Eloquent\Model;

class ViaticoPago extends Model
{
    use NormalizesMontosDosDecimales;

    protected $table = 'viaticos_pagos';

    protected $fillable = [
        'viatico_id',
        'concepto',
        'monto',
        'file_path',
        'file_url',
        'file_size',
        'file_original_name',
        'file_mime_type',
        'file_extension',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function viatico()
    {
        return $this->belongsTo(Viatico::class);
    }
}
