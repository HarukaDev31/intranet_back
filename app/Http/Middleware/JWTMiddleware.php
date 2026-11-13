<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Log;

class JWTMiddleware
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
            // Multipart a veces llega sin Authorization en proxies; aceptar token en query/body.
            if (!$request->headers->has('Authorization')) {
                $token = $request->bearerToken()
                    ?: $request->input('token')
                    ?: $request->query('token');
                if (is_string($token) && $token !== '') {
                    $request->headers->set('Authorization', 'Bearer ' . $token);
                }
            }

            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                Log::info('Usuario no encontrado');
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado'
                ], 401);
            }

        } catch (TokenExpiredException $e) {
            Log::info('Token expirado');
            return response()->json([
                'status' => 'error',
                'message' => 'Token expirado'
            ], 401);
        } catch (TokenInvalidException $e) {
            Log::info('Token inválido');
            return response()->json([
                'status' => 'error',
                'message' => 'Token inválido'
            ], 401);
        } catch (JWTException $e) {
            Log::info('Token no proporcionado');
            return response()->json([
                'status' => 'error',
                'message' => 'Token no proporcionado'
            ], 401);
        }

        return $next($request);
    }
}
