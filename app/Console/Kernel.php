<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Ejecutar la notificación diaria a las 02:00
        $schedule->command('notify:arrive-date-today')->dailyAt('02:00');
        $schedule->command('clientes:populate --force')->dailyAt('03:00');
        // Ejecutar auto-firma de contratos cada 5 minutos
        $schedule->command('contracts:auto-sign')->everyFiveMinutes();
        // Reintentar sincronización Bitrix de leads landing pendientes (solo si hay webhook)
        $schedule->command('landing:enqueue-bitrix-sync')
            ->everyMinute()
            ->when(function () {
                $url = config('services.bitrix.webhook_url');

                return !empty($url) && is_string($url);
            });
        // Sincroniza calculadoras COTIZADO -> CONFIRMADO segun estado de cotizacion vinculada
        $schedule->command('calculadora:sync-cotizado-a-confirmado')
            ->everyFiveMinutes()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
