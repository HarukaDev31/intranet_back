<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioDevice extends Model
{
    protected $table = 'usuario_device';

    protected $fillable = [
        'id_usuario',
        'fcm_token',
        'platform',
        'device_id',
        'app_version',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'ID_Usuario');
    }
}
