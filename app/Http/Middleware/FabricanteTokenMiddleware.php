<?php

namespace App\Http\Middleware;

use App\Services\Fabricante\FabricanteSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FabricanteTokenMiddleware
{
    public function __construct(
        private readonly FabricanteSessionService $sessionService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json([
                'success' => false,
                'message' => 'Token no proporcionado',
            ], 401);
        }

        $plainToken = trim(substr($header, 7));
        $session = $this->sessionService->findActiveSessionByToken($plainToken);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido o expirado',
            ], 401);
        }

        $user = $session->user;

        if (! $user || ! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario inactivo',
            ], 403);
        }

        $this->sessionService->touchSession($session);

        $request->attributes->set('fabricante_user', $user);
        $request->attributes->set('fabricante_session', $session);

        return $next($request);
    }
}
