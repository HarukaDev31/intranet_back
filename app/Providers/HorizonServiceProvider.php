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
        // Configurar Horizon para que solo sea accesible en entorno local
        if ($this->app->environment('local')) {
            Horizon::auth(function ($request) {
                return true;
            });
        }
    }
}
