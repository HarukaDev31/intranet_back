<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;

class BroadcastingAuth
{
    public function handle(Request $request, Closure $next)
    {
        try {
            if ($request->hasHeader('Authorization')) {
                $token = str_replace('Bearer ', '', $request->header('Authorization'));
                $user = JWTAuth::setToken($token)->authenticate();
                Auth::login($user);
            } else if ($request->has('token')) {
                $token = $request->get('token');
                $user = JWTAuth::setToken($token)->authenticate();
                Auth::login($user);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
