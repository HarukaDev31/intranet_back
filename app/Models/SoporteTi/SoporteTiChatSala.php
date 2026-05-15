<?php

namespace App\Models\SoporteTi;

use Illuminate\Database\Eloquent\Model;

class SoporteTiChatSala extends Model
{
    protected $table = 'soporte_ti_chat_salas';

    protected $fillable = [
        'chat_uuid',
        'solicitud_id',
    ];

    public function solicitud()
    {
        return $this->belongsTo(SoporteTiSolicitud::class, 'solicitud_id');
    }

    public function mensajes()
    {
        return $this->hasMany(SoporteTiMensaje::class, 'sala_id');
    }

    public function miembros()
    {
        return $this->hasMany(SoporteTiChatMiembro::class, 'sala_id');
    }
}
