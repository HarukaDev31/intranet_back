<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\Landing\LandingCursoLeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LandingCursoLeadController extends Controller
{
    /** @var LandingCursoLeadService */
    protected $landingCursoLeadService;

    public function __construct(LandingCursoLeadService $landingCursoLeadService)
    {
        $this->landingCursoLeadService = $landingCursoLeadService;
    }

    /**
     * Lead desde landing curso (probusiness_curso_landing).
     * Protegido con middleware landing.curso.form_token (Bearer).
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('LandingCursoLeadController: request recibida', [
            'ip' => $request->ip(),
            'codigo_campana' => $request->input('codigo_campana'),
        ]);

        $validator = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:64'],
            'email' => ['required', 'email', 'max:255'],
            'experiencia_importando' => ['required', 'string', 'in:si,no,poca'],
            'codigo_campana' => ['nullable', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            Log::warning('LandingCursoLeadController: validación fallida', [
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json([
                'message' => 'Datos no válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $lead = $this->landingCursoLeadService->store(
            $validator->validated(),
            $request
        );

        Log::info('LandingCursoLeadController: lead registrado', [
            'lead_id' => $lead->id,
        ]);

        return response()->json([
            'message' => 'Registro recibido. Un asesor te contactará pronto.',
            'id' => $lead->id,
        ], 201);
    }
}
