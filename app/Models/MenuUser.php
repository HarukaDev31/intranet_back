<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuUser extends Model
{
    protected $table = 'menu_user';
    protected $primaryKey = 'ID_Menu';

    protected $fillable = [
        'ID_Padre',
        'Nu_Orden',
        'No_Menu',
        'No_Menu_Url',
        'No_Class_Controller',
        'Txt_Css_Icons',
        'Nu_Separador',
        'Nu_Seguridad',
        'Nu_Activo',
        'Nu_Tipo_Sistema',
        'Txt_Url_Video',
        'No_Menu_China',
        'url_intranet_v2',
        'show_father',
    ];

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'ID_Padre', 'ID_Menu');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'ID_Padre', 'ID_Menu');
    }

    public function accesos(): HasMany
    {
        return $this->hasMany(MenuUserAccess::class, 'ID_Menu', 'ID_Menu');
    }
}
