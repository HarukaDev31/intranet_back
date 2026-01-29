<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViaticoPago extends Model
{
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
        'monto' => 'decimal:2',
        'file_size' => 'integer',
    ];

    public function viatico()
    {
        return $this->belongsTo(Viatico::class);
    }
}
