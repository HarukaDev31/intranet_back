<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * @return void
     */
    public function register()
    {
        $this->hideSensitiveRequestDetails();

        // Local: todo. Prod: solo requests (consumo endpoints), logs y excepciones.
        Telescope::filter(function (IncomingEntry $entry) {
            if ($this->app->environment('local')) {
                return true;
            }

            return in_array($entry->type, ['request', 'log', 'exception'], true);
        });
    }

    /**
     * @return void
     */
    protected function hideSensitiveRequestDetails()
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * @return void
     */
    protected function authorization()
    {
        $this->gate();

        Telescope::auth(function ($request) {
            if ($this->app->environment('local')) {
                return true;
            }

            // Sesión de login interno (cookie) — sin token en la URL
            if ($request->session()->get('telescope_authenticated')) {
                return true;
            }

            $token = (string) config('telescope.dashboard_token', '');
            if ($token !== '') {
                $provided = (string) ($request->query('token') ?? $request->header('X-Telescope-Token', ''));
                if ($provided !== '' && hash_equals($token, $provided)) {
                    return true;
                }
            }

            $allowedIps = config('telescope.allowed_ips', []);
            if (is_array($allowedIps) && $allowedIps !== [] && in_array($request->ip(), $allowedIps, true)) {
                return true;
            }

            $user = $request->user();

            return $user !== null;
        });
    }

    /**
     * @return void
     */
    protected function gate()
    {
        Gate::define('viewTelescope', function ($user) {
            return $user !== null;
        });
    }
}
