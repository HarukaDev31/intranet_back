<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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
        Event::listen(ConnectionEstablished::class, function ($event) {
            try {
                $event->connection->statement("SET time_zone = '-05:00';");
            } catch (\Throwable $e) {
                Log::warning('No se pudo establecer zona horaria MySQL: ' . $e->getMessage());
            }
        });
    }
}
