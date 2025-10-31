<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Traits\WhatsappTrait;
use Illuminate\Support\Facades\Log;

class SendRecordatorioDatosProveedorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait;

    protected $idCotizacion;
    protected $idContainer;
    protected $proveedores;

    /**
     * Create a new job instance.
     *
     * @param int $idCotizacion
     * @param int $idContainer
     * @param array $proveedores
     */
    public function __construct($idCotizacion, $idContainer, $proveedores)
    {
        $this->idCotizacion = $idCotizacion;
        $this->idContainer = $idContainer;
        $this->proveedores = $proveedores;
        
        // ✅ Delay de 3 segundos para asegurar que los datos estén actualizados en BD
        $this->delay(now()->addSeconds(3));
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $cotizacion = Cotizacion::find($this->idCotizacion);
            $uuid=$cotizacion->uuid;
            if (!$cotizacion) {
                Log::error('Cotización no encontrada para ID: ' . $this->idCotizacion);
                return;
            }

            $nombreCliente = $cotizacion->nombre;
            $url = env('APP_URL_DATOS_PROVEEDOR');
            $url = $url . '/' . $uuid;
            $message = "Hola @nombrecliente necesitamos los datos de tu vendedor para que nuestro equipo de China se encarge de recibir tu carga.\n\nPor favor ingresa al enlace y colocar los datos del vendedor.\n\nIngresar aquí: @url\n\n";
            $message = str_replace('@nombrecliente', $nombreCliente, $message);
            $message = str_replace('@url', $url, $message);
            
            foreach ($this->proveedores as $proveedorId) {
                $proveedor = CotizacionProveedor::find($proveedorId);
                
                if ($proveedor) {
                    $message .= "Nombre del vendedor: " . $proveedor->supplier . "\n";
                    $message .= "WeChat: " . $proveedor->supplier_phone . "\n";
                    $message .= "Codigo vendedor: " . $proveedor->code_supplier . "\n";
                    $message .= "----------------------------------------------------------\n";
                }
            }
            
            $message .= "Quedo atenta.";
            $telefono = preg_replace('/\s+/', '', $cotizacion->telefono);
            $this->phoneNumberId = $telefono ? $telefono . '@c.us' : '';
            Log::info('Telefono: ' . $this->phoneNumberId);
            $this->sendMessage($message, $this->phoneNumberId);
            
            Log::info('Recordatorio de datos de proveedor enviado correctamente', [
                'id_cotizacion' => $this->idCotizacion,
                'id_container' => $this->idContainer,
                'proveedores' => $this->proveedores
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en SendRecordatorioDatosProveedorJob: ' . $e->getMessage(), [
                'id_cotizacion' => $this->idCotizacion,
                'id_container' => $this->idContainer,
                'proveedores' => $this->proveedores,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
}
