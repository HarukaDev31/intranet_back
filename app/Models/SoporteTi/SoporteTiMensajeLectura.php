<?php

namespace App\Models\SoporteTi;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class SoporteTiMensajeLectura extends Model
{
    protected $table = 'soporte_ti_mensaje_lecturas';

    public $timestamps = false;

    protected $fillable = [
        'mensaje_id',
        'usuario_id',
        'leido_en',
    ];

    protected $casts = [
        'leido_en' => 'datetime',
    ];

    public function mensaje()
    {
        return $this->belongsTo(SoporteTiMensaje::class, 'mensaje_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'ID_Usuario');
    }
}
