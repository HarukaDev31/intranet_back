<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\Cotizacion;
use Carbon\Carbon;
use App\Http\Controllers\CargaConsolidada\CotizacionProveedorController;
use Illuminate\Support\Facades\Log;
class NotifyArriveDateToday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:arrive-date-today';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notificar por WhatsApp a clientes cuya arrive_date (o arrive_date_china) es hoy';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Buscando proveedores con arrive_date para hoy...');

        $today = Carbon::now()->format('Y-m-d');

        $providers = CotizacionProveedor::where(function ($q) use ($today) {
            $q->whereDate('arrive_date_china', $today)
              ->orWhereDate('arrive_date', $today);
        })->get();

        $controller = new CotizacionProveedorController();
        $count = 0;

        foreach ($providers as $prov) {
            // Prefiere arrive_date_china si existe
            $dateToCheck = null;
            if (!empty($prov->arrive_date_china) && !in_array($prov->arrive_date_china, ['0000-00-00','0000-00-00 00:00:00'], true)) {
                $dateToCheck = Carbon::parse($prov->arrive_date_china)->format('Y-m-d');
            } elseif (!empty($prov->arrive_date) && !in_array($prov->arrive_date, ['0000-00-00','0000-00-00 00:00:00'], true)) {
                $dateToCheck = Carbon::parse($prov->arrive_date)->format('Y-m-d');
            }

            if ($dateToCheck !== $today) {
                continue;
            }

            // Call controller method to perform notification logic
            try {
                // We create a fake request and call the method; the controller method expects auth user via JWT,
                // but notifyIfArriveDateIsToday does a JWT check; to avoid requiring JWT here we'll call the internal logic directly.
                $cotizacion = Cotizacion::find($prov->id_cotizacion);
                $clienteNombre = $cotizacion->nombre ?? 'Cliente';
                $providerCode = $prov->code_supplier ?? $prov->supplier_code ?? '';
                $telefono = $cotizacion->telefono ?? '';
                Log::info('Telefono: ' . $telefono);
                $mensaje = "Hola 👋 {$clienteNombre} la carga de tu proveedor {$providerCode} aun no llega a nuestro almacen de China, ¿tienes alguna noticia por parte de tu proveedor?";

                // The controller uses sendMessage via trait; instantiate controller and call sendMessage
                $resultado = $controller->sendMessage($mensaje, $this->formatPhoneNumber($telefono));
                $this->info('Enviado a proveedor id=' . $prov->id . ' resultado: ' . json_encode($resultado));
                $count++;
            } catch (\Exception $e) {
                $this->error('Error notificando proveedor id=' . $prov->id . ': ' . $e->getMessage());
            }
        }

        $this->info("Notificaciones enviadas: $count");

        return 0;
    }

    /**
     * Format phone number similar to controller::formatPhoneNumber
     * @param string $telefono
     * @return string
     */
    private function formatPhoneNumber($telefono)
    {
        // Remove non-digits
        $telefono = preg_replace('/[^0-9]/', '', $telefono);

        // If no country code and looks like Peruvian local number (9 digits) add 51
        if (strlen($telefono) === 9) {
            $telefono = '51' . $telefono;
        } elseif (strlen($telefono) === 10 && substr($telefono, 0, 1) === '0') {
            // If it's 10 and starts with 0, drop leading 0 and add country code 51
            $telefono = '51' . ltrim($telefono, '0');
        }

        return $telefono . '@c.us';
    }
}
