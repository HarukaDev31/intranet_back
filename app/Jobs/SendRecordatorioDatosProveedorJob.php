<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Support\WhatsApp\CoordinacionWhatsappPayload;
use App\Traits\WhatsappTrait;
use App\Traits\DatabaseConnectionTrait;
use Illuminate\Support\Facades\Log;

class SendRecordatorioDatosProveedorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait, DatabaseConnectionTrait;

    protected $idCotizacion;
    protected $idContainer;
    protected $proveedores;
    protected $domain;

    /**
     * @param  array<int, int>  $proveedores
     */
    public function __construct($idCotizacion, $idContainer, $proveedores, $domain = null)
    {
        $this->idCotizacion = $idCotizacion;
        $this->idContainer = $idContainer;
        $this->proveedores = $proveedores;
        $this->domain = $domain;
        $this->delay(now()->addSeconds(3));
    }

    public function handle(): void
    {
        try {
            $this->setDatabaseConnection($this->domain);
            $cotizacion = Cotizacion::find($this->idCotizacion);
            if (!$cotizacion) {
                Log::error('Cotización no encontrada para ID: ' . $this->idCotizacion);

                return;
            }

            $nombreCliente = $cotizacion->nombre;
            $listaProveedores = '';

            foreach ($this->proveedores as $proveedorId) {
                $proveedor = CotizacionProveedor::find($proveedorId);

                if ($proveedor) {
                    $listaProveedores .= "Nombre del vendedor: " . $proveedor->supplier . "\n";
                    $listaProveedores .= "Número o WeChat: " . $proveedor->supplier_phone . "\n";
                    $listaProveedores .= "Codigo proveedor: " . $proveedor->code_supplier . "\n";
                    $listaProveedores .= "----------------------------------------------------------\n";
                }
            }

            $bitrix = "Hola {$nombreCliente} necesitamos los datos de tu proveedor para que nuestro equipo de China se encargue de recibir tu carga.\n\n"
                . "Por favor contacta al vendedor y envía los datos del proveedor.\n\n"
                . $listaProveedores
                . "Quedo atenta.";

            $telefono = preg_replace('/\s+/', '', $cotizacion->telefono ?? '');
            $this->phoneNumberId = $telefono !== '' ? $telefono . '@c.us' : '';
            Log::info('Telefono: ' . $this->phoneNumberId);

            $this->sendMessage(
                $bitrix,
                $this->phoneNumberId,
                0,
                'consolidado',
                CoordinacionWhatsappPayload::proveedorInspeccionManual(
                    (string) $this->phoneNumberId,
                    $bitrix,
                    $bitrix
                )
            );

            Log::info('Recordatorio de datos de proveedor enviado correctamente', [
                'id_cotizacion' => $this->idCotizacion,
                'id_container' => $this->idContainer,
                'proveedores' => $this->proveedores,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en SendRecordatorioDatosProveedorJob: ' . $e->getMessage(), [
                'id_cotizacion' => $this->idCotizacion,
                'id_container' => $this->idContainer,
                'proveedores' => $this->proveedores,
            ]);

            throw $e;
        }
    }
}
