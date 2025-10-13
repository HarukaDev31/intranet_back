<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeliveryConfirmationProvinceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $carga;
    public $mensaje;
    public $deliveryForm;
    public $cotizacion;
    public $user;
    public $tipoDocumento;
    public $nombreRazonSocial;
    public $departamento;
    public $provincia;
    public $distrito;
    public $logo_header_white;
    public $logo_footer;

    /**
     * Create a new message instance.
     */
    public function __construct($mensaje, $deliveryForm, $cotizacion, $user, $tipoDocumento, $nombreRazonSocial, $carga, $departamento, $provincia, $distrito, $logo_header, $logo_footer)
    {
        $this->mensaje = $mensaje;
        $this->deliveryForm = $deliveryForm;
        $this->cotizacion = $cotizacion;
        $this->user = $user;
        $this->tipoDocumento = $tipoDocumento;
        $this->nombreRazonSocial = $nombreRazonSocial;
        $this->carga = $carga;
        $this->departamento = $departamento;
        $this->provincia = $provincia;
        $this->distrito = $distrito;
        $this->logo_header_white = $logo_header;
        $this->logo_footer = $logo_footer;
    }

    /**
     * Build the message.
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
                'nombreRazonSocial' => $this->nombreRazonSocial,
                'carga' => $this->carga,
                'departamento' => $this->departamento,
                'provincia' => $this->provincia,
                'distrito' => $this->distrito,
                'logo_header' => $this->logo_header_white,
                'logo_footer' => $this->logo_footer,
            ]);
    }
}
