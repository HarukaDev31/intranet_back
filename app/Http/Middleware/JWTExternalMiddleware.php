<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Log;

class JWTExternalMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try {
            // Configurar el guard para usuarios externos
            config(['auth.defaults.guard' => 'api-external']);
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                Log::info('Usuario externo no encontrado');
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado'
                ], 401);
            }
            
        } catch (TokenExpiredException $e) {
            Log::info('Token expirado (usuario externo)');
            return response()->json([
                'status' => 'error',
                'message' => 'Token expirado'
            ], 401);
        } catch (TokenInvalidException $e) {
            Log::info('Token inválido (usuario externo)');
            return response()->json([
                'status' => 'error',
                'message' => 'Token inválido'
            ], 401);
        } catch (JWTException $e) {
            Log::info('Token no proporcionado (usuario externo)');
            return response()->json([
                'status' => 'error',
                'message' => 'Token no proporcionado'
            ], 401);
        }

        return $next($request);
    }
}
