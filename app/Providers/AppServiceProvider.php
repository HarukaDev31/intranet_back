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
        //
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
