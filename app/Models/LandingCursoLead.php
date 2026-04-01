<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingCursoLead extends Model
{
    protected $table = 'landing_curso_leads';

    protected $fillable = [
        'nombre',
        'whatsapp',
        'email',
        'experiencia_importando',
        'codigo_campana',
        'ip_address',
        'user_agent',
    ];
}
