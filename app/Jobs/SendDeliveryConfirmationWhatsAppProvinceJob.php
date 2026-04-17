<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Traits\WhatsappTrait;
use App\Traits\DatabaseConnectionTrait;
use App\Models\CargaConsolidada\ConsolidadoDeliveryFormProvince;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Services\Delivery\ProvinciaEntregaNotificacionService;

class SendDeliveryConfirmationWhatsAppProvinceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait, DatabaseConnectionTrait;

    protected $deliveryFormId;
    protected $domain;

    /**
     * Create a new job instance.
     *
     * @param int $deliveryFormId
     * @param string|null $domain
     * @return void
     */
    public function __construct($deliveryFormId, $domain = null)
    {
        $this->deliveryFormId = $deliveryFormId;
        $this->domain = $domain;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // Establecer la conexión de BD basándose en el dominio
            $this->setDatabaseConnection($this->domain);
            // Obtener el formulario de delivery con las relaciones necesarias
            $deliveryForm = ConsolidadoDeliveryFormProvince::with([
                'cotizacion',
                'departamento',
                'provincia',
                'distrito',
                'agency'
            ])->find($this->deliveryFormId);

            if (!$deliveryForm) {
                Log::error('Formulario de delivery de provincia no encontrado', ['id' => $this->deliveryFormId]);
                return;
            }

            $idUser = $deliveryForm->id_user;
            $user = User::find($idUser);

            // Obtener la cotización
            $cotizacion = $deliveryForm->cotizacion;
            if (!$cotizacion) {
                Log::error('Cotización no encontrada para el formulario de provincia', ['form_id' => $this->deliveryFormId]);
                return;
            }

            // Obtener la carga del contenedor
            $contenedor = Contenedor::find($cotizacion->id_contenedor);
            if (!$contenedor) {
                Log::error('Contenedor no encontrado para la cotización', ['cotizacion_id' => $cotizacion->id]);
                return;
            }
            $carga = $contenedor->carga;

            // Verificar que la cotización tenga teléfono
            if (empty($cotizacion->telefono)) {
                Log::warning('La cotización no tiene teléfono para enviar WhatsApp', [
                    'cotizacion_id' => $cotizacion->id,
                    'uuid' => $cotizacion->uuid
                ]);
                return;
            }

            // Formatear el teléfono (agregar código de país si es necesario)
            $telefono = $this->formatPhoneNumber($cotizacion->telefono);

            $notificacion = ProvinciaEntregaNotificacionService::datosVistaCorreo($deliveryForm, $carga, $user);
            $mensaje = $notificacion['whatsapp'];

            // Enviar el mensaje de WhatsApp
            $resultado = $this->sendMessage($mensaje, $telefono);
            
            // Enviar email de confirmación si el usuario tiene email
            if ($user && $user->email) {
                Mail::to($user->email)->send(new \App\Mail\DeliveryConfirmationProvinceMail(
                    $deliveryForm,
                    $cotizacion,
                    $user,
                    $carga,
                    public_path('storage/logo_icons/logo_header_white.png'),
                    public_path('storage/logo_icons/logo_footer.png'),
                    $notificacion
                ));
            }

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
