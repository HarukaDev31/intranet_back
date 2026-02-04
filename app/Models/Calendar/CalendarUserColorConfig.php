<?php

namespace App\Models\Calendar;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarUserColorConfig extends Model
{
    protected $table = 'calendar_user_color_config';

    protected $fillable = [
        'calendar_id',
        'user_id',
        'color_code',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class, 'calendar_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'user_id', 'ID_Usuario');
    }
}
