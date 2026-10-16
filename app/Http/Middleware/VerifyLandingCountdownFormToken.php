<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Acepta Bearer de curso o consolidado para el endpoint público del contador.
 */
class VerifyLandingCountdownFormToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $cursoToken = config('landing_curso.form_token');
        $consolidadoToken = config('landing_consolidado.form_token');

        $validTokens = array_values(array_filter([
            is_string($cursoToken) ? $cursoToken : null,
            is_string($consolidadoToken) ? $consolidadoToken : null,
        ], function ($token) {
            return is_string($token) && $token !== '';
        }));

        if ($validTokens === []) {
            return response()->json([
                'message' => 'Servicio de leads no configurado.',
            ], 503);
        }

        $header = (string) $request->header('Authorization', '');
        if (strpos($header, 'Bearer ') !== 0) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        $token = substr($header, 7);
        if ($token === '') {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        foreach ($validTokens as $expected) {
            if (hash_equals($expected, $token)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'No autorizado.'], 401);
    }
}
