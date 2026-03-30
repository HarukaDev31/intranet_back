<?php

namespace App\Services\Landing;

use App\Models\LandingConsolidadoLead;
use Illuminate\Http\Request;

class LandingConsolidadoLeadService
{
    public function store(array $data, ?Request $request = null): LandingConsolidadoLead
    {
        $payload = [
            'nombre' => $data['nombre'],
            'whatsapp' => $data['whatsapp'],
            'proveedor' => $data['proveedor'],
            'codigo_campana' => $data['codigo_campana'],
        ];

        if ($request) {
            $payload['ip_address'] = $request->ip();
            $payload['user_agent'] = substr((string) $request->userAgent(), 0, 2000);
        }

        return LandingConsolidadoLead::query()->create($payload);
    }
}
