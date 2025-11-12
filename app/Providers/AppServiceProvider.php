<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

    public function boot(): void
    {
        DB::purge('mysql'); // Limpia caché de conexión
        DB::reconnect('mysql'); // Reabre conexión con el timezone correcto

        DB::connection('mysql')->setQueryGrammar(DB::connection('mysql')->getQueryGrammar());
        DB::connection('mysql')->setPostProcessor(DB::connection('mysql')->getPostProcessor());

        try {
            DB::connection('mysql')->statement("SET time_zone = '-05:00'");
            Log::info('Zona horaria MySQL configurada correctamente (-05:00)');
        } catch (\Throwable $e) {
            Log::warning('No se pudo establecer zona horaria MySQL: ' . $e->getMessage());
        }
    }
}
