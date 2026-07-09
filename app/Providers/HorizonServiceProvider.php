<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

class HorizonServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if (! class_exists(\Laravel\Horizon\Horizon::class)) {
            return;
        }

        Horizon::auth(function ($request) {
            if ($this->app->environment('local')) {
                return true;
            }

            if ($this->app->environment('qa')) {
                return true;
            }

            // Tras ?token= en la primera carga, la SPA llama /horizon/api/* sin query string.
            if ($request->session()->get('horizon_authenticated') === true) {
                return true;
            }

            $token = (string) config('horizon.dashboard_token', '');
            if ($token !== '') {
                $provided = (string) ($request->query('token') ?? $request->header('X-Horizon-Token', ''));
                if ($provided !== '' && hash_equals($token, $provided)) {
                    $request->session()->put('horizon_authenticated', true);

                    return true;
                }
            }

            $allowedIps = config('horizon.allowed_ips', []);

            return $allowedIps !== [] && in_array($request->ip(), $allowedIps, true);
        });
    }
}
