<?php

namespace App\Models\SoporteTi;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class SoporteTiChatMiembro extends Model
{
    protected $table = 'soporte_ti_chat_miembros';

    public $timestamps = false;

    protected $fillable = [
        'sala_id',
        'usuario_id',
        'rol_en_ticket',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'ID_Usuario');
    }
}
