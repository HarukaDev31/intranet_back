<?php

namespace App\Services\Landing;

use App\Models\LandingCursoLead;
use Illuminate\Http\Request;

class LandingCursoLeadService
{
    public function store(array $data, ?Request $request = null): LandingCursoLead
    {
        $payload = [
            'nombre' => $data['nombre'],
            'whatsapp' => $data['whatsapp'],
            'email' => $data['email'],
            'experiencia_importando' => $data['experiencia_importando'],
        ];

        if ($request) {
            $payload['ip_address'] = $request->ip();
            $payload['user_agent'] = substr((string) $request->userAgent(), 0, 2000);
        }

        return LandingCursoLead::query()->create($payload);
    }
}
