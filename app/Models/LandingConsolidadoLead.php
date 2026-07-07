<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LandingConsolidadoLead extends Model
{
    public const MAX_BITRIX_SYNC_FAILURES = 3;

    public const FORM_SOURCE_LANDING_V2 = 'landing_consolidado_v2';

    public const FORM_SOURCE_PROBUSINESS_PE = 'probusiness_pe';

    protected $table = 'landing_consolidado_leads';

    protected $fillable = [
        'nombre',
        'whatsapp',
        'proveedor',
        'codigo_campana',
        'form_source',
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
