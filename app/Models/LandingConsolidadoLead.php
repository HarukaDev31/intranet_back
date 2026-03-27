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
        'ip_address',
        'user_agent',
    ];
}
