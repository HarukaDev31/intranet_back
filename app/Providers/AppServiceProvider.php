<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\CargaConsolidada\Cotizacion;
use App\Observers\CargaConsolidada\CotizacionObserver;

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
        // Registrar observer para sincronizar estados entre Cotizacion y CalculadoraImportacion
        Cotizacion::observe(CotizacionObserver::class);
    }
}
