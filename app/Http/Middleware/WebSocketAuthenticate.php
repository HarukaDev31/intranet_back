<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class WebSocketAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        try {
            if (!$token = $request->query('token')) {
                throw new UnauthorizedHttpException('jwt-auth', 'Token not provided');
            }

            $user = JWTAuth::setToken($token)->authenticate();
            if (!$user) {
                throw new UnauthorizedHttpException('jwt-auth', 'User not found');
            }

            return $next($request);
        } catch (Exception $e) {
            Log::error('WebSocket Authentication Error: ' . $e->getMessage());
            return response()->json(['message' => 'Unauthorized'], 401);
        }
    }
}
