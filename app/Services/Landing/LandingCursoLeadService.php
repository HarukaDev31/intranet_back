<?php

namespace App\Services\Landing;

use App\Jobs\Crm\SyncLandingLeadToCrmJob;
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
            'codigo_campana' => isset($data['codigo_campana'])
                ? (trim((string) $data['codigo_campana']) ?: null)
                : null,
        ];

        if ($request) {
            $payload['ip_address'] = $request->ip();
            $payload['user_agent'] = substr((string) $request->userAgent(), 0, 2000);
        }

        $lead = LandingCursoLead::query()->create($payload);

        if (config('services.bitrix.webhook_url') && ($this->cursoBitrixSyncEnabled())) {
            SyncLandingLeadToCrmJob::dispatch('curso', $lead->id);
        }

        return $lead;
    }

    /**
     * Misma idea que consolidado: con webhook se encola; LANDING_CURSO_BITRIX_ENABLED=false desactiva solo curso.
     */
    private function cursoBitrixSyncEnabled()
    {
        $cfg = config('landing_curso.bitrix', []);

        return (bool) ($cfg['enabled'] ?? true);
    }
}
