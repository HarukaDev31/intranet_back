<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\CargaConsolidada\HomeStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class HomeStatsController extends Controller
{
    /**
     * GET /carga-consolidada/home-stats
     * Almacén China: todas las orgs. Socio: solo las suyas.
     */
    public function index(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Usuario no autenticado'], 401);
            }

            $orgIds = $user->organizacionesPermitidas();
            $esAlmacen = in_array($user->getNombreGrupo(), Usuario::ROLES_VISIBILIDAD_GLOBAL, true);

            $data = (new HomeStatsService())->resumen($orgIds, $esAlmacen);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en home-stats: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron cargar los indicadores',
            ], 500);
        }
    }
}
