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
        // Si Horizon no está instalado, salir sin error (Windows / PHP sin pcntl)
        if (! class_exists(\Laravel\Horizon\Horizon::class)) {
            return;
        }

        // Configurar Horizon para que solo sea accesible en entorno local
        if ($this->app->environment('local')) {
            Horizon::auth(function ($request) {
                return true;
            });
        }
    }
}
