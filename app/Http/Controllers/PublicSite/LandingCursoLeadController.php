<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\Landing\LandingCursoLeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $validator = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:64'],
            'email' => ['required', 'email', 'max:255'],
            'experiencia_importando' => ['required', 'string', 'in:si,no,poca'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos no válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $lead = $this->landingCursoLeadService->store(
            $validator->validated(),
            $request
        );

        return response()->json([
            'message' => 'Registro recibido. Un asesor te contactará pronto.',
            'id' => $lead->id,
        ], 201);
    }
}
