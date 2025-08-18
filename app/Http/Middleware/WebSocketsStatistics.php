<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class WebSocketsStatistics
{
    public function handle(Request $request, Closure $next)
    {
        // Permitir todas las solicitudes de estadísticas desde localhost
        if ($request->getHost() === 'localhost' || $request->getHost() === '127.0.0.1') {
            return $next($request);
        }

        // Para solicitudes no locales, verificar autenticación
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
