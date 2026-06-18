<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThirdPartyTokenAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('third_party.read_only_token');

        if (! is_string($expected) || $expected === '') {
            return response()->json([
                'message' => 'Integración de terceros no configurada.',
            ], 503);
        }

        $header = (string) $request->header('Authorization', '');
        if (strpos($header, 'Bearer ') !== 0) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        $token = substr($header, 7);
        if ($token === '' || ! hash_equals($expected, $token)) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        return $next($request);
    }
}
