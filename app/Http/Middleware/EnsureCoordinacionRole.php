<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureCoordinacionRole
{
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

        if (!$user || $user->getNombreGrupo() !== Usuario::ROL_COORDINACION) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso restringido al rol Coordinación',
            ], 403);
        }

        return $next($request);
    }
}
