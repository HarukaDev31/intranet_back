<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Traits\WhatsappTrait;
use App\Models\CargaConsolidada\ConsolidadoDeliveryFormLima;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class SendDeliveryConfirmationWhatsAppLimaJob implements ShouldQueue
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
            $deliveryForm = ConsolidadoDeliveryFormLima::with(['cotizacion'])->find($this->deliveryFormId);
            $idUser = $deliveryForm->id_user;
            $user = User::find($idUser);
            if (!$deliveryForm) {
                Log::error('Formulario de delivery de Lima no encontrado', ['id' => $this->deliveryFormId]);
                return;
            }

            // Obtener la cotización
            $cotizacion = $deliveryForm->cotizacion;
            //Obtener la carga del contenedor
            $contenedor = Contenedor::find($cotizacion->id_contenedor);
            $carga = $contenedor->carga;
            if (!$cotizacion) {
                Log::error('Cotización no encontrada para el formulario de Lima', ['form_id' => $this->deliveryFormId]);
                return;
            }
            if (!$contenedor) {
                Log::error('Contenedor no encontrado para la cotización del formulario de Lima', ['cotizacion_id' => $cotizacion->id]);
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

            $telefono = $this->formatPhoneNumber($cotizacion->telefono);

            // Obtener información de fecha y hora usando Query Builder
            $rangeInfo = DB::table('consolidado_delivery_range_date as r')
                ->join('consolidado_delivery_date as d', 'r.id_date', '=', 'd.id')
                ->where('r.id', $deliveryForm->id_range_date)
                ->select('d.day', 'd.month', 'd.year', 'r.start_time', 'r.end_time')
                ->first();

            // Formatear fecha y hora
            $fechaRecojo = $rangeInfo ? sprintf('%02d/%02d/%04d', $rangeInfo->day, $rangeInfo->month, $rangeInfo->year) : 'Fecha no especificada';
            $horaRecojo = $rangeInfo ? sprintf('%s - %s', $rangeInfo->start_time, $rangeInfo->end_time) : 'Hora no especificada';

            // Construir el mensaje con fecha y hora
            $mensaje = "Consolidado #{$carga}\n\n";
            $mensaje .= "Tu reserva se realizó exitosamente, tu fecha de recojo es \"{$fechaRecojo}\" en el horario \"{$horaRecojo}\".\n";
            $mensaje .= "La persona que recogerá su pedido es {$deliveryForm->pick_name} - DNI {$deliveryForm->pick_doc}.\n\n";
            $mensaje .= "🏢 Dirección de recojo: Calle Rio Nazca 243- San Luis. Ref. Al costado de la Agencia Antezana\n\n";

            // Enviar el mensaje de WhatsApp
            $resultado = $this->sendMessage($mensaje, $telefono);
            
            // Enviar email de confirmación si el usuario tiene email
            if ($user && $user->email) {
                \Mail::to($user->email)->send(new \App\Mail\DeliveryConfirmationLimaMail(
                    $mensaje,
                    $deliveryForm,
                    $cotizacion,
                    $user,
                    $fechaRecojo,
                    $horaRecojo,
                    public_path('storage/logo_header.png'),
                    public_path('storage/logo_footer.png')
                ));
            }
            if ($resultado['status']) {
                Log::info('Mensaje de WhatsApp enviado exitosamente a cliente de Lima', [
                    'cotizacion_id' => $cotizacion->id,
                    'telefono' => $telefono,
                    'delivery_form_id' => $this->deliveryFormId
                ]);
            } else {
                Log::error('Error al enviar mensaje de WhatsApp a cliente de Lima', [
                    'cotizacion_id' => $cotizacion->id,
                    'telefono' => $telefono,
                    'delivery_form_id' => $this->deliveryFormId,
                    'error' => $resultado['response']
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error en SendDeliveryConfirmationWhatsAppLimaJob', [
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
