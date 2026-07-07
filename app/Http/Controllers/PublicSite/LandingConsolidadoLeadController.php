<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\Landing\LandingConsolidadoLeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LandingConsolidadoLeadController extends Controller
{
    /** @var LandingConsolidadoLeadService */
    protected $landingConsolidadoLeadService;

    public function __construct(LandingConsolidadoLeadService $landingConsolidadoLeadService)
    {
        $this->landingConsolidadoLeadService = $landingConsolidadoLeadService;
    }

    /**
     * Lead desde landing consolidado (probusiness_consolidado_landing).
     * Protegido con middleware landing.consolidado.form_token (Bearer).
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeRequest($request);

        Log::info('LandingConsolidadoLeadController: request recibida', [
            'ip' => $request->ip(),
            'codigo_campana' => $request->input('codigo_campana'),
            'form_source' => $request->input('form_source'),
        ]);

        $validator = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:64'],
            'proveedor' => ['required', 'string', 'in:si,no,buscando'],
            'codigo_campana' => ['required', 'string', 'max:32'],
            'form_source' => ['nullable', 'string', 'max:64', 'in:landing_consolidado_v2,probusiness_pe'],
        ]);

        if ($validator->fails()) {
            Log::warning('LandingConsolidadoLeadController: validación fallida', [
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json([
                'message' => 'Datos no válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $lead = $this->landingConsolidadoLeadService->store(
            $validator->validated(),
            $request
        );

        Log::info('LandingConsolidadoLeadController: lead registrado', [
            'lead_id' => $lead->id,
        ]);

        return response()->json([
            'message' => 'Registro recibido. Un asesor te contactará pronto.',
            'id' => $lead->id,
        ], 201);
    }

    /**
     * Compatibilidad con payloads legacy (CodeIgniter / landing Astro).
     */
    private function normalizeRequest(Request $request): void
    {
        $merge = [];

        if (! $request->filled('codigo_campana') && $request->filled('campaign_code')) {
            $merge['codigo_campana'] = $request->input('campaign_code');
        }

        if (! $request->filled('proveedor') && $request->filled('proveedor_china')) {
            $merge['proveedor'] = $request->input('proveedor_china');
        }

        if (! empty($merge)) {
            $request->merge($merge);
        }
    }
}
