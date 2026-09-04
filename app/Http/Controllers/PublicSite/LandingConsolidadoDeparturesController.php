<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\CargaConsolidada\Contenedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class LandingConsolidadoDeparturesController extends Controller
{
    /**
     * Próximas fechas de cierre de contenedores pendientes (estado_china = PENDIENTE).
     * Protegido con middleware landing.consolidado.form_token (Bearer).
     * Resultado cacheado 1h para no golpear la BD en cada build/deploy de la landing.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 2);
        $limit = max(1, min($limit, 6));

        $data = Cache::remember("landing_consolidado:next_departures:{$limit}", 3600, function () use ($limit) {
            return Contenedor::query()
                ->where('estado_china', Contenedor::CONTEDOR_PENDIENTE)
                ->where('empresa', '!=', 1)
                ->whereNotNull('f_cierre')
                ->whereDate('f_cierre', '>=', Carbon::today())
                ->orderBy('f_cierre')
                ->limit($limit)
                ->get(['id', 'carga', 'f_cierre'])
                ->map(fn (Contenedor $contenedor) => [
                    'id' => $contenedor->id,
                    'carga' => (int) $contenedor->carga,
                    'closeDate' => Carbon::parse($contenedor->f_cierre)->format('Y-m-d'),
                ])
                ->values();
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
