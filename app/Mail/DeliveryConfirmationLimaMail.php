<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeliveryConfirmationLimaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $mensaje;
    public $deliveryForm;
    public $cotizacion;
    public $user;
    public $fechaRecojo;
    public $horaRecojo;
    public $carga;
    public $logo_header;
    public $logo_footer;

    /**
     * Create a new message instance.
     */
    public function __construct($mensaje, $deliveryForm, $cotizacion, $user, $fechaRecojo, $horaRecojo, $carga, $logo_header, $logo_footer)
    {
        $this->mensaje = $mensaje;
        $this->deliveryForm = $deliveryForm;
        $this->cotizacion = $cotizacion;
        $this->user = $user;
        $this->fechaRecojo = $fechaRecojo;
        $this->horaRecojo = $horaRecojo;
        $this->carga = $carga;
        $this->logo_header = $logo_header;
        $this->logo_footer = $logo_footer;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Confirmación de Recojo - Lima - Consolidado #' . $this->carga)
            ->view('emails.delivery_confirmation_lima')
            ->with([
                'mensaje' => $this->mensaje,
                'deliveryForm' => $this->deliveryForm,
                'cotizacion' => $this->cotizacion,
                'user' => $this->user,
                'fechaRecojo' => $this->fechaRecojo,
                'horaRecojo' => $this->horaRecojo,
                'carga' => $this->carga,
                'logo_header' => $this->logo_header,
                'logo_footer' => $this->logo_footer,
            ]);
    }
}
