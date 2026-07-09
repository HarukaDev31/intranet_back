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

            $token = (string) env('HORIZON_DASHBOARD_TOKEN', '');
            if ($token !== '') {
                $provided = (string) ($request->query('token') ?? $request->header('X-Horizon-Token', ''));
                if ($provided !== '' && hash_equals($token, $provided)) {
                    return true;
                }
            }

            $allowedIps = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('HORIZON_ALLOWED_IPS', ''))
            )));

            return $allowedIps !== [] && in_array($request->ip(), $allowedIps, true);
        });
    }
}
