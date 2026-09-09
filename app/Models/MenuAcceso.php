<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuAcceso extends Model
{
    protected $table = 'menu_acceso';
    protected $primaryKey = 'ID_Menu_Grupo_Usuario';
    public $timestamps = false;

    protected $fillable = [
        'ID_Empresa',
        'ID_Menu',
        'ID_Grupo_Usuario',
        'Nu_Consultar',
        'Nu_Agregar',
        'Nu_Editar',
        'Nu_Eliminar',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'ID_Menu', 'ID_Menu');
    }

    public function grupoUsuario(): BelongsTo
    {
        return $this->belongsTo(GrupoUsuario::class, 'ID_Grupo_Usuario', 'ID_Grupo_Usuario');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'ID_Empresa', 'ID_Empresa');
    }
}
