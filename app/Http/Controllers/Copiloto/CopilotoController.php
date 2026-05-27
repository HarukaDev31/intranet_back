<?php

namespace App\Http\Controllers\Copiloto;

use App\Http\Controllers\Controller;
use App\Services\Copiloto\CopilotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CopilotoController extends Controller
{
    /** @var CopilotoService */
    protected $copilotoService;

    public function __construct(CopilotoService $copilotoService)
    {
        $this->copilotoService = $copilotoService;
    }

    public function leads(Request $request)
    {
        try {
            return response()->json($this->copilotoService->getLeads($request->all()));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar leads del copiloto: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function conversacion(Request $request, $phone)
    {
        try {
            return response()->json($this->copilotoService->getConversacion($phone, $request->all()));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar la conversación: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function ficha($phone)
    {
        try {
            return response()->json($this->copilotoService->getFicha($phone));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar la ficha del lead: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function responder(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Endpoint pendiente de integración con Evolution/Bitrix fallback.',
        ], 501);
    }

    public function syncEstado()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'sync_historico' => Cache::get('copiloto:sync_historico:status'),
                'sync_historico_last_completed_at' => Cache::get('copiloto:sync_historico:last_completed_at'),
                'evolution_healthy' => Cache::get('copiloto:evolution:healthy'),
                'bitrix_healthy' => Cache::get('copiloto:bitrix:healthy'),
            ],
        ]);
    }
}

