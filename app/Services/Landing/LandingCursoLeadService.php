<?php

namespace App\Services\Landing;

use App\Jobs\Crm\SyncLandingLeadToCrmJob;
use App\Models\LandingCursoLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LandingCursoLeadService
{
    public function store(array $data, ?Request $request = null): LandingCursoLead
    {
        Log::info('LandingCursoLeadService: iniciando almacenamiento de lead', [
            'codigo_campana' => $data['codigo_campana'] ?? null,
            'has_request' => (bool) $request,
        ]);

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

        Log::info('LandingCursoLeadService: lead creado', [
            'lead_id' => $lead->id,
            'codigo_campana' => $lead->codigo_campana,
        ]);

        if (config('services.bitrix.webhook_url') && ($this->cursoBitrixSyncEnabled())) {
            SyncLandingLeadToCrmJob::dispatch('curso', $lead->id);
            Log::info('LandingCursoLeadService: job CRM encolado', [
                'lead_id' => $lead->id,
                'tipo' => 'curso',
            ]);
        } else {
            Log::info('LandingCursoLeadService: CRM curso deshabilitado o sin webhook', [
                'lead_id' => $lead->id,
                'bitrix_enabled' => $this->cursoBitrixSyncEnabled(),
                'has_webhook' => (bool) config('services.bitrix.webhook_url'),
            ]);
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
