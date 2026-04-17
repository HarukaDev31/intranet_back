<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Services\Delivery\ProvinciaEntregaNotificacionService;

class DeliveryConfirmationProvinceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $carga;
    public $mensaje;
    public $deliveryForm;
    public $cotizacion;
    public $user;
    public $tipoDocumento;
    public $nombreDestinatario;
    public $numeroDocumento;
    public $celularDestinatario;
    public $nombreAgenciaTransporte;
    public $rucAgenciaTransporte;
    public $destinoLinea;
    public $entregaEn;
    public $direccionEntrega;
    public $primerNombre;
    public $logo_header_white;
    public $logo_footer;

    /**
     * @param mixed $deliveryForm
     * @param mixed $cotizacion
     * @param mixed $user
     * @param string|int $carga
     * @param string $logo_header
     * @param string $logo_footer
     * @param array|null $notificacion Salida de ProvinciaEntregaNotificacionService::datosVistaCorreo (opcional; se recalcula si es null)
     */
    public function __construct($deliveryForm, $cotizacion, $user, $carga, $logo_header, $logo_footer, $notificacion = null)
    {
        $this->deliveryForm = $deliveryForm;
        $this->cotizacion = $cotizacion;
        $this->user = $user;
        $this->carga = $carga;
        $this->logo_header_white = $logo_header;
        $this->logo_footer = $logo_footer;

        if ($notificacion === null) {
            $notificacion = ProvinciaEntregaNotificacionService::datosVistaCorreo($deliveryForm, $carga, $user);
        }

        $this->mensaje = $notificacion['whatsapp'];
        $this->tipoDocumento = $notificacion['tipoDocumento'];
        $this->nombreDestinatario = $notificacion['nombreDestinatario'];
        $this->numeroDocumento = $notificacion['numeroDocumento'];
        $this->celularDestinatario = $notificacion['celularDestinatario'];
        $this->nombreAgenciaTransporte = $notificacion['nombreAgencia'];
        $this->rucAgenciaTransporte = $notificacion['rucAgencia'];
        $this->destinoLinea = $notificacion['destinoLinea'];
        $this->entregaEn = $notificacion['entregaEn'];
        $this->direccionEntrega = $notificacion['direccionEntrega'];
        $this->primerNombre = $notificacion['primerNombre'];
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Confirmación de Envío - Provincia - Consolidado #' . $this->carga)
            ->view('emails.delivery_confirmation_province')
            ->with([
                'carga' => $this->carga,
                'mensaje' => $this->mensaje,
                'deliveryForm' => $this->deliveryForm,
                'cotizacion' => $this->cotizacion,
                'user' => $this->user,
                'tipoDocumento' => $this->tipoDocumento,
                'nombreDestinatario' => $this->nombreDestinatario,
                'numeroDocumento' => $this->numeroDocumento,
                'celularDestinatario' => $this->celularDestinatario,
                'nombreAgenciaTransporte' => $this->nombreAgenciaTransporte,
                'rucAgenciaTransporte' => $this->rucAgenciaTransporte,
                'destinoLinea' => $this->destinoLinea,
                'entregaEn' => $this->entregaEn,
                'direccionEntrega' => $this->direccionEntrega,
                'primerNombre' => $this->primerNombre,
                'logo_header' => $this->logo_header_white,
                'logo_footer' => $this->logo_footer,
            ]);
    }
}
