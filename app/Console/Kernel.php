<?php

namespace App\Console;

use App\Services\CargaConsolidada\SeguimientoConsolidadoCorteConfig;
use Carbon\Carbon;
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
       /* $schedule->command('landing:enqueue-bitrix-sync')
            ->everyMinute()
            ->when(function () {
                $url = config('services.bitrix.webhook_url');

                return !empty($url) && is_string($url);
            });
            */
        // Sincroniza calculadoras COTIZADO -> CONFIRMADO segun estado de cotizacion vinculada
        $schedule->command('calculadora:sync-cotizado-a-confirmado')
            ->everyFiveMinutes()
            ->withoutOverlapping();
        // INSPECCIONADO→RESERVADO si pagos LOGÍSTICA completan meta y contenedor.estado_china ≠ COMPLETADO (reintenta tras cambios de monto)
        $schedule->command('carga-consolidada:promote-inspeccionados-reservados-pagos')
            ->everyFiveMinutes()
            ->withoutOverlapping();
        // R/NC/WAIT → INSPECTION si ya hay archivos en contenedor_consolidado_almacen_inspection
        $schedule->command('proveedores:promote-inspection-from-almacen')
            ->everyFiveMinutes()
            ->withoutOverlapping();
        // Excel seguimiento consolidado en Drive: auto-vincular #11-2026 en adelante
       /* $schedule->command('segimiento-consolidado:vincular')
            ->everyFiveMinutes()
            ->withoutOverlapping();
            */
        // Excel seguimiento consolidado en Drive: sincronización de respaldo (no cada 5 min)
       /* $schedule->command('segimiento-consolidado:sync-linked')
            ->everyThirtyMinutes()
            ->withoutOverlapping();
            */
        // Corte diario — hora desde system_configs (excel_seguimiento_hora_corte)
       /* $schedule->command('segimiento-consolidado:corte-datos-proveedor')
            ->everyMinute()
            ->timezone(config('carga_consolidada.seguimiento_corte_timezone', 'America/Lima'))
            ->when(function () {
                $settings = SeguimientoConsolidadoCorteConfig::settings();

                return Carbon::now($settings['timezone'])->format('H:i') === $settings['hora'];
            })
            ->withoutOverlapping();
            */
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
