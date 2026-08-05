<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * Exige sesión de login interno antes de entrar a Telescope (cookie, sin token en URL).
 */
class TelescopeSessionAuth
{
    /**
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        // Fallbacks: token de dashboard o IP permitida (sin pasar por el form).
        $dashboardToken = (string) config('telescope.dashboard_token', '');
        if ($dashboardToken !== '') {
            $provided = (string) ($request->query('token') ?? $request->header('X-Telescope-Token', ''));
            if ($provided !== '' && hash_equals($dashboardToken, $provided)) {
                return $next($request);
            }
        }

        $allowedIps = config('telescope.allowed_ips', []);
        if (is_array($allowedIps) && $allowedIps !== [] && in_array($request->ip(), $allowedIps, true)) {
            return $next($request);
        }

        if (Session::has('telescope_authenticated')) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => 'Unauthenticated.'], 403);
        }

        return redirect()->guest(route('telescope.login'));
    }
}
