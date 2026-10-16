<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\Landing\LandingConsolidadoCountdownService;
use Illuminate\Http\JsonResponse;

class LandingConsolidadoCountdownController extends Controller
{
    /** @var LandingConsolidadoCountdownService */
    protected $countdownService;

    public function __construct(LandingConsolidadoCountdownService $countdownService)
    {
        $this->countdownService = $countdownService;
    }

    /**
     * Fecha fin del contador para la landing curso.
     * Protegido con middleware landing.countdown.form_token (Bearer curso o consolidado).
     * Resultado cacheado en Redis vía Cache::remember.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->countdownService->getPublicPayload(),
        ]);
    }
}
