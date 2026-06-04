<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureCopilotoWaAccess
{
    /** ID Jefe Ventas (acceso Copiloto equipo). */
    const JEFE_VENTAS_ID = 28791;

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        $grupo = $user->getNombreGrupo();
        $userId = (int) $user->getIdUsuario();

        $allowed = $grupo === Usuario::ROL_COTIZADOR
            || $grupo === Usuario::ROL_ADMINISTRACION
            || $grupo === Usuario::ROL_GERENCIA
            || $userId === self::JEFE_VENTAS_ID;

        if (!$allowed) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso restringido a Copiloto / Ventas',
            ], 403);
        }

        return $next($request);
    }
}
