<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class LogViewerAuth
{
    /**
     * Handle an incoming request.
     * Permite acceso al visor de logs solo a usuarios autenticados mediante sesión
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Verificar si el usuario está autenticado en la sesión
        if (!Session::has('logviewer_authenticated')) {
            // Redirigir al formulario de login
            return redirect()->route('logviewer.login');
        }

        return $next($request);
    }
}

