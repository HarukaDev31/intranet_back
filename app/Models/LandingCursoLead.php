<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LandingCursoLead extends Model
{
    public const MAX_BITRIX_SYNC_FAILURES = 3;

    protected $table = 'landing_curso_leads';

    protected $fillable = [
        'nombre',
        'whatsapp',
        'email',
        'experiencia_importando',
        'codigo_campana',
        'ip_address',
        'user_agent',
        'bitrix_synced_at',
        'bitrix_sync_errors',
        'bitrix_sync_failed_at',
        'bitrix_sync_last_error',
    ];

    protected $casts = [
        'bitrix_synced_at' => 'datetime',
        'bitrix_sync_failed_at' => 'datetime',
        'bitrix_sync_errors' => 'integer',
    ];

    public function scopePendingBitrixSync(Builder $query): Builder
    {
        return $query
            ->whereNull('bitrix_synced_at')
            ->whereNull('bitrix_sync_failed_at')
            ->where('bitrix_sync_errors', '<', self::MAX_BITRIX_SYNC_FAILURES);
    }
}
