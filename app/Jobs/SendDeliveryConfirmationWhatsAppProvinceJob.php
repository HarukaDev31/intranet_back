<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Traits\WhatsappTrait;
use App\Models\CargaConsolidada\ConsolidadoDeliveryFormProvince;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

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
            // Obtener el formulario de delivery con las relaciones necesarias
            $deliveryForm = ConsolidadoDeliveryFormProvince::with([
                'cotizacion',
                'departamento',
                'provincia',
                'distrito',
                'agency'
            ])->find($this->deliveryFormId);
            
            $idUser = $deliveryForm->id_user;
            $user = User::find($idUser);
            
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

            //Obtener la carga del contenedor
            $contenedor = Contenedor::find($cotizacion->id_contenedor);
            $carga = $contenedor->carga;
            if (!$contenedor) {
                Log::error('Contenedor no encontrado para la cotización', ['cotizacion_id' => $cotizacion->id]);
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

            // Formatear el teléfono (agregar código de país si es necesario)
            $telefono = $this->formatPhoneNumber($cotizacion->telefono);

            // Determinar el tipo de documento y nombre/razón social
            $tipoDocumento = $deliveryForm->r_type === 'PERSONA NATURAL' ? 'DNI' : 'RUC';
            $nombreRazonSocial = $deliveryForm->r_name;

            // Obtener información de ubicación
            $departamento = $deliveryForm->departamento ? $deliveryForm->departamento->No_Departamento : 'N/A';
            $provincia = $deliveryForm->provincia ? $deliveryForm->provincia->No_Provincia : 'N/A';
            $distrito = $deliveryForm->distrito ? $deliveryForm->distrito->No_Distrito : 'N/A';

            //Obtener información del tipo de agencia
            if ($deliveryForm->id_agency) {
                $agencyModel = \App\Models\DeliveryAgency::find($deliveryForm->id_agency);
                if ($agencyModel && $agencyModel->name) {
                    $tipoAgencia = $agencyModel->name;
                }
            } else {
                $tipoAgencia = 'N/A';
            }
            

            // Construir el mensaje
            $mensaje = "Consolidado #{$carga}\n\n";
            $mensaje .= "Tu reserva se realizó exitosamente.\n\n";
            $mensaje .= "El cosignatario a quien se enviará la carga es:\n";
            $mensaje .= "{$tipoDocumento}: {$deliveryForm->r_doc}\n";
            $mensaje .= "Nombre: {$nombreRazonSocial}\n\n";
            $mensaje .= "📍 *Ubicación:*\n";
            $mensaje .= "Departamento: {$departamento}\n";
            $mensaje .= "Provincia: {$provincia}\n";
            $mensaje .= "Distrito: {$distrito}\n\n";
            $mensaje .= "Tipo de agencia: {$tipoAgencia}\n\n";
            if ($deliveryForm->id_agency == 3) {
                $agencyName = $deliveryForm->agency_name ?? '';
                $agencyRuc = $deliveryForm->agency_ruc ?? '';
                $mensaje .= "Agencia: {$agencyName}\n";
                $mensaje .= "RUC Agencia: {$agencyRuc}\n\n";
            }
            // Enviar el mensaje de WhatsApp
            $resultado = $this->sendMessage($mensaje, $telefono);
            
            // Enviar email de confirmación si el usuario tiene email
            if ($user && $user->email) {
                Mail::to($user->email)->send(new \App\Mail\DeliveryConfirmationProvinceMail(
                    $mensaje,
                    $deliveryForm,
                    $cotizacion,
                    $user,
                    $tipoDocumento,
                    $nombreRazonSocial,
                    $carga,
                    $departamento,
                    $provincia,
                    $distrito,
                    public_path('storage/logo_icons/logo_header_white.png'),
                    public_path('storage/logo_icons/logo_footer.png')
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
