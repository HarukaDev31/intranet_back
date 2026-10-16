<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Services\Landing\LandingConsolidadoCountdownService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LandingCountdownAdminController extends Controller
{
    /** @var LandingConsolidadoCountdownService */
    protected $countdownService;

    public function __construct(LandingConsolidadoCountdownService $countdownService)
    {
        $this->countdownService = $countdownService;
    }

    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->countdownService->getAdminPayload(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ends_at' => ['required', 'date'],
        ]);

        try {
            $data = $this->countdownService->updateEndsAt((string) $validated['ends_at']);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo guardar la fecha del contador: ' . $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Fecha del contador actualizada.',
            'data' => $data,
        ]);
    }
}
