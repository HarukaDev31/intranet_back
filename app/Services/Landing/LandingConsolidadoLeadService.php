<?php

namespace App\Services\Landing;

use App\Jobs\Crm\SyncLandingLeadToCrmJob;
use App\Models\LandingConsolidadoLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LandingConsolidadoLeadService
{
    public function store(array $data, ?Request $request = null): LandingConsolidadoLead
    {
        Log::info('LandingConsolidadoLeadService: iniciando almacenamiento de lead', [
            'codigo_campana' => $data['codigo_campana'] ?? null,
            'has_request' => (bool) $request,
        ]);

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

        $lead = LandingConsolidadoLead::query()->create($payload);

        Log::info('LandingConsolidadoLeadService: lead creado', [
            'lead_id' => $lead->id,
            'codigo_campana' => $lead->codigo_campana,
        ]);

        if (config('services.bitrix.webhook_url')) {
            SyncLandingLeadToCrmJob::dispatch('consolidado', $lead->id);
            Log::info('LandingConsolidadoLeadService: job CRM encolado', [
                'lead_id' => $lead->id,
                'tipo' => 'consolidado',
            ]);
        } else {
            Log::info('LandingConsolidadoLeadService: CRM deshabilitado (sin webhook)', [
                'lead_id' => $lead->id,
            ]);
        }

        return $lead;
    }
}
