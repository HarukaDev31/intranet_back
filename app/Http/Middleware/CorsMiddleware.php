<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Log para debug
        Log::info('CorsMiddleware ejecutándose para: ' . $request->path());
        
        // Manejar preflight requests
        if ($request->getMethod() === "OPTIONS") {
            Log::info('Manejando preflight request');
            return response('', 200)
                ->header('Access-Control-Allow-Origin', 'http://localhost:3001')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        $response = $next($request);
        
        // Aplicar CORS a rutas que empiecen con /storage/ o /files/
        if (strpos($request->path(), 'storage/') === 0 || strpos($request->path(), 'files/') === 0) {
            Log::info('Aplicando CORS headers para storage/files');
            $response->headers->set('Access-Control-Allow-Origin', 'http://localhost:3001');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
        
        return $response;
    }
}
