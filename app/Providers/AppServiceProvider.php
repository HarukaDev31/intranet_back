<?php

namespace App\Providers;

use App\Models\CargaConsolidada\Cotizacion;
use App\Observers\CargaConsolidada\CotizacionObserver;
use App\Support\Database\WslLocalDatabaseConnection;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if (
            class_exists(\Laravel\Telescope\Telescope::class)
            && filter_var(env('TELESCOPE_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
        ) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        WslLocalDatabaseConnection::applyForQueueWorkers();

        // Registrar observer para sincronizar estados entre Cotizacion y CalculadoraImportacion
        Cotizacion::observe(CotizacionObserver::class);
    }
}
