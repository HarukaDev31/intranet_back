<?php

namespace App\Models\Calendar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarActivity extends Model
{
    protected $table = 'calendar_activities';

    protected $fillable = ['name', 'orden', 'color_code', 'allow_saturday', 'allow_sunday', 'default_priority'];

    protected $casts = [
        'allow_saturday' => 'boolean',
        'allow_sunday' => 'boolean',
        'default_priority' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'activity_id');
    }
}
