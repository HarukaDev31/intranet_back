<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViaticoRetribucion extends Model
{
    protected $table = 'viaticos_retribuciones';

    protected $fillable = [
        'viatico_id',
        'file_path',
        'file_original_name',
        'banco',
        'monto',
        'fecha_cierre',
        'orden',
        'sended_at',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_cierre' => 'date',
        'sended_at' => 'datetime',
    ];

    public function viatico()
    {
        return $this->belongsTo(Viatico::class);
    }
}
