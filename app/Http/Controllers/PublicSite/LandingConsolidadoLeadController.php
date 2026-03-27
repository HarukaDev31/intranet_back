<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\Landing\LandingConsolidadoLeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $validator = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:64'],
            'proveedor' => ['required', 'string', 'in:si,no,buscando'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos no válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $lead = $this->landingConsolidadoLeadService->store(
            $validator->validated(),
            $request
        );

        return response()->json([
            'message' => 'Registro recibido. Un asesor te contactará pronto.',
            'id' => $lead->id,
        ], 201);
    }
}
