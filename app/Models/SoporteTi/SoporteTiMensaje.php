<?php

namespace App\Models\SoporteTi;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class SoporteTiMensaje extends Model
{
    protected $table = 'soporte_ti_mensajes';

    protected $fillable = [
        'sala_id',
        'usuario_id',
        'remitente',
        'iniciales',
        'color',
        'texto',
        'es_sistema',
        'reply_to_id',
        'archivo_nombre',
    ];

    protected $casts = [
        'es_sistema' => 'boolean',
    ];

    public function sala()
    {
        return $this->belongsTo(SoporteTiChatSala::class, 'sala_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'ID_Usuario');
    }

    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function imagenes()
    {
        return $this->hasMany(SoporteTiMensajeImagen::class, 'mensaje_id')->orderBy('orden');
    }
}
