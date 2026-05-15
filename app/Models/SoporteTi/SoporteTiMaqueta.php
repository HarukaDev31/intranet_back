<?php

namespace App\Models\SoporteTi;

use Illuminate\Database\Eloquent\Model;

class SoporteTiMaqueta extends Model
{
    protected $table = 'soporte_ti_maquetas';

    protected $fillable = [
        'solicitud_id',
        'nombre',
        'tamano',
        'ruta_archivo',
        'url_preview',
        'fecha_entrega',
        'aprobada',
        'subida_por_user_id',
    ];

    protected $casts = [
        'aprobada' => 'boolean',
        'fecha_entrega' => 'date',
    ];

    public function solicitud()
    {
        return $this->belongsTo(SoporteTiSolicitud::class, 'solicitud_id');
    }
}
