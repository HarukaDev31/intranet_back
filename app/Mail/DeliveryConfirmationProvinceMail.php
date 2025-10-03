<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeliveryConfirmationProvinceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $mensaje;
    public $deliveryForm;
    public $cotizacion;
    public $user;
    public $tipoDocumento;
    public $nombreRazonSocial;
    public $logo_header;
    public $logo_footer;

    /**
     * Create a new message instance.
     */
    public function __construct($mensaje, $deliveryForm, $cotizacion, $user, $tipoDocumento, $nombreRazonSocial, $logo_header, $logo_footer)
    {
        $this->mensaje = $mensaje;
        $this->deliveryForm = $deliveryForm;
        $this->cotizacion = $cotizacion;
        $this->user = $user;
        $this->tipoDocumento = $tipoDocumento;
        $this->nombreRazonSocial = $nombreRazonSocial;
        $this->logo_header = $logo_header;
        $this->logo_footer = $logo_footer;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Confirmación de Envío - Provincia')
            ->view('emails.delivery_confirmation_province')
            ->with([
                'mensaje' => $this->mensaje,
                'deliveryForm' => $this->deliveryForm,
                'cotizacion' => $this->cotizacion,
                'user' => $this->user,
                'tipoDocumento' => $this->tipoDocumento,
                'nombreRazonSocial' => $this->nombreRazonSocial,
                'logo_header' => $this->logo_header,
                'logo_footer' => $this->logo_footer,
            ]);
    }
}
