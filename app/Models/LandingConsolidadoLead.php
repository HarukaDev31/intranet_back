<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingConsolidadoLead extends Model
{
    protected $table = 'landing_consolidado_leads';

    protected $fillable = [
        'nombre',
        'whatsapp',
        'proveedor',
        'codigo_campana',
        'ip_address',
        'user_agent',
        'bitrix_synced_at',
    ];

    protected $casts = [
        'bitrix_synced_at' => 'datetime',
    ];
}
