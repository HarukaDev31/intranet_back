<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebSocketsDashboardAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Aquí puedes agregar lógica adicional para verificar roles específicos
        // Por ejemplo, verificar si el usuario es admin

        return $next($request);
    }
}
