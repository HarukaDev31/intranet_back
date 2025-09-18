<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        // Para APIs, no redirigir sino devolver null para que se maneje como JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }
        
        // Si necesitas una ruta de login para web (opcional)
        // return route('login');
        return null;
    }
}
