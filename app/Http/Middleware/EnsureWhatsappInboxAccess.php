<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureWhatsappInboxAccess
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

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        $grupo = $user->getNombreGrupo();
        $allowed = in_array($grupo, [
            Usuario::ROL_COORDINACION,
            Usuario::ROL_CONTABILIDAD,
        ], true);

        if (!$allowed) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso restringido al WhatsApp Inbox',
            ], 403);
        }

        return $next($request);
    }
}
