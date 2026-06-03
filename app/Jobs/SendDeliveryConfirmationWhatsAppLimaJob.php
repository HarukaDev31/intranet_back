<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Support\WhatsApp\CoordinacionWhatsappPayload;
use App\Traits\WhatsappTrait;
use App\Models\CargaConsolidada\ConsolidadoDeliveryFormLima;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\User;
use App\Services\Delivery\LimaRecojoNotificacionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Traits\MailTrait;

class SendDeliveryConfirmationWhatsAppLimaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait, MailTrait;

    protected $deliveryFormId;

    /**
     * @param int $deliveryFormId
     * @return void
     */
    public function __construct($deliveryFormId)
    {
        $this->deliveryFormId = $deliveryFormId;
    }

    /**
     * @return void
     */
    public function handle()
    {
        try {
            $deliveryForm = ConsolidadoDeliveryFormLima::with(['cotizacion'])->find($this->deliveryFormId);
            if (!$deliveryForm) {
                Log::error('Formulario de delivery de Lima no encontrado', ['id' => $this->deliveryFormId]);
                return;
            }

            $user = User::find($deliveryForm->id_user);
            $cotizacion = $deliveryForm->cotizacion;
            if (!$cotizacion) {
                Log::error('Cotización no encontrada para el formulario de Lima', ['form_id' => $this->deliveryFormId]);
                return;
            }

            $contenedor = Contenedor::find($cotizacion->id_contenedor);
            if (!$contenedor) {
                Log::error('Contenedor no encontrado para la cotización del formulario de Lima', ['cotizacion_id' => $cotizacion->id]);
                return;
            }
            $carga = $contenedor->carga;

            if (empty($cotizacion->telefono)) {
                Log::warning('La cotización no tiene teléfono para enviar WhatsApp', [
                    'cotizacion_id' => $cotizacion->id,
                    'uuid' => $cotizacion->uuid,
                ]);
                return;
            }

            $telefono = $this->formatPhoneNumber($cotizacion->telefono);

            $notificacion = LimaRecojoNotificacionService::datosVistaCorreo($deliveryForm, $carga, $user);
            $mensaje = $notificacion['whatsapp'];

            if (config('meta_whatsapp.coordinacion_enabled')) {
                $fechaHora = trim(
                    ($notificacion['fechaTextual'] ?? '') . ' · ' . ($notificacion['horaRecojo'] ?? '') . ' hrs',
                    ' ·'
                );
                $resultado = $this->queueCoordinacionWhatsApp(CoordinacionWhatsappPayload::confirmLima(
                    $telefono,
                    [
                        'primer_nombre' => $notificacion['primerNombre'],
                        'carga' => (string) $carga,
                        'pick_name' => $notificacion['pickName'],
                        'pick_dni' => $notificacion['pickDoc'],
                        'pick_phone' => $notificacion['pickPhone'],
                        'fecha_hora_recojo' => $fechaHora,
                        'direccion' => $notificacion['direccion'],
                        'referencia' => $notificacion['referencia'],
                        'maps_url' => $notificacion['mapsUrl'],
                    ],
                    $mensaje
                ));
            } else {
                $resultado = $this->sendMessage($mensaje, $telefono);
            }

            if ($user && $user->email) {
                $email=$this->sendMailTo($user->email, new \App\Mail\DeliveryConfirmationLimaMail(
                    $deliveryForm,
                    $cotizacion,
                    $user,
                    $carga,
                    public_path('storage/logo_icons/logo_header_white.png'),
                    public_path('storage/logo_icons/logo_footer.png'),
                    $notificacion
                ));
                Log::info('email', ['email' => $email]);
            }

            if ($resultado['status']) {
                Log::info('Mensaje de WhatsApp enviado exitosamente a cliente de Lima', [
                    'cotizacion_id' => $cotizacion->id,
                    'telefono' => $telefono,
                    'delivery_form_id' => $this->deliveryFormId,
                ]);
            } else {
                Log::error('Error al enviar mensaje de WhatsApp a cliente de Lima', [
                    'cotizacion_id' => $cotizacion->id,
                    'telefono' => $telefono,
                    'delivery_form_id' => $this->deliveryFormId,
                    'error' => $resultado['response'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error en SendDeliveryConfirmationWhatsAppLimaJob', [
                'delivery_form_id' => $this->deliveryFormId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Formatea el número de teléfono para WhatsApp (id @c.us).
     *
     * @param string $telefono
     * @return string
     */
    private function formatPhoneNumber($telefono)
    {
        $telefono = preg_replace('/[^0-9]/', '', $telefono);

        if (strlen($telefono) === 9) {
            $telefono = '51' . $telefono;
        } elseif (strlen($telefono) === 10 && substr($telefono, 0, 1) === '0') {
            $telefono = '51' . substr($telefono, 1);
        }

        return $telefono . '@c.us';
    }
}
