<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuUserAccess extends Model
{
    protected $table = 'menu_user_access';
    protected $primaryKey = 'ID_Menu_User_Access';

    protected $fillable = [
        'ID_Menu',
        'user_id',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(MenuUser::class, 'ID_Menu', 'ID_Menu');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
