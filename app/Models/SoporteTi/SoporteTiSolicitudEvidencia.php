<?php

namespace App\Models\SoporteTi;

use Illuminate\Database\Eloquent\Model;

class SoporteTiSolicitudEvidencia extends Model
{
    protected $table = 'soporte_ti_solicitud_evidencias';

    protected $fillable = array(
        'solicitud_id',
        'mensaje_id',
        'tipo',
        'texto',
        'url',
        'nombre',
        'tamano',
        'mime',
        'orden',
    );

    public function solicitud()
    {
        return $this->belongsTo(SoporteTiSolicitud::class, 'solicitud_id');
    }

    public function mensaje()
    {
        return $this->belongsTo(SoporteTiMensaje::class, 'mensaje_id');
    }
}
