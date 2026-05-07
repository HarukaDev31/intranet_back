<?php

namespace App\Models;

use App\Models\Concerns\NormalizesMontosDosDecimales;
use Illuminate\Database\Eloquent\Model;

class ViaticoRetribucion extends Model
{
    use NormalizesMontosDosDecimales;

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
        'fecha_cierre' => 'date',
        'sended_at' => 'datetime',
    ];

    public function viatico()
    {
        return $this->belongsTo(Viatico::class);
    }
}
