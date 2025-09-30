<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Traits\WhatsappTrait;
use App\Models\CargaConsolidada\ConsolidadoDeliveryFormProvince;
use App\Models\CargaConsolidada\Cotizacion;
use Illuminate\Support\Facades\Log;

class SendDeliveryConfirmationWhatsAppProvinceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait;

    protected $deliveryFormId;

    /**
     * Create a new job instance.
     *
     * @param int $deliveryFormId
     * @return void
     */
    public function __construct($deliveryFormId)
    {
        $this->deliveryFormId = $deliveryFormId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // Obtener el formulario de delivery
            $deliveryForm = ConsolidadoDeliveryFormProvince::with(['cotizacion'])->find($this->deliveryFormId);
            
            if (!$deliveryForm) {
                Log::error('Formulario de delivery de provincia no encontrado', ['id' => $this->deliveryFormId]);
                return;
            }

            // Obtener la cotización
            $cotizacion = $deliveryForm->cotizacion;
            if (!$cotizacion) {
                Log::error('Cotización no encontrada para el formulario de provincia', ['form_id' => $this->deliveryFormId]);
                return;
            }

            // Verificar que la cotización tenga teléfono
            if (empty($cotizacion->telefono)) {
                Log::warning('La cotización no tiene teléfono para enviar WhatsApp', [
                    'cotizacion_id' => $cotizacion->id,
                    'uuid' => $cotizacion->uuid
                ]);
                return;
            }

            $cotizacion->telefono = '51912705923';

            // Formatear el teléfono (agregar código de país si es necesario)
            $telefono = $this->formatPhoneNumber($cotizacion->telefono);

            // Determinar el tipo de documento y nombre/razón social
            $tipoDocumento = $deliveryForm->r_type === 'PERSONA NATURAL' ? 'DNI' : 'RUC';
            $nombreRazonSocial = $deliveryForm->r_name;

            // Construir el mensaje
            $mensaje = "Tu reserva se realizó exitosamente.\n";
            $mensaje .= "El cosignatario a quien se enviará la carga es: {$tipoDocumento}: {$deliveryForm->r_doc} - Nombre {$nombreRazonSocial}.";

            // Enviar el mensaje de WhatsApp
            $resultado = $this->sendMessage($mensaje, $telefono);

            if ($resultado['status']) {
                Log::info('Mensaje de WhatsApp enviado exitosamente a cliente de provincia', [
                    'cotizacion_id' => $cotizacion->id,
                    'telefono' => $telefono,
                    'delivery_form_id' => $this->deliveryFormId
                ]);
            } else {
                Log::error('Error al enviar mensaje de WhatsApp a cliente de provincia', [
                    'cotizacion_id' => $cotizacion->id,
                    'telefono' => $telefono,
                    'delivery_form_id' => $this->deliveryFormId,
                    'error' => $resultado['response']
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error en SendDeliveryConfirmationWhatsAppProvinceJob', [
                'delivery_form_id' => $this->deliveryFormId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Formatear número de teléfono para WhatsApp
     *
     * @param string $telefono
     * @return string
     */
    private function formatPhoneNumber($telefono)
    {
        // Remover espacios y caracteres especiales
        $telefono = preg_replace('/[^0-9]/', '', $telefono);
        
        // Si no tiene código de país, agregar +51 (Perú)
        if (strlen($telefono) === 9) {
            $telefono = '51' . $telefono;
        } elseif (strlen($telefono) === 10 && substr($telefono, 0, 1) === '0') {
            $telefono = '51' . substr($telefono, 1);
        }
        
        return $telefono . '@c.us';
    }
}
