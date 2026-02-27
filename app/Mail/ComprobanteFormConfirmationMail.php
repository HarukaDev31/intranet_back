<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComprobanteFormConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $form;
    public $cotizacion;
    public $carga;

    public function __construct($form, $cotizacion, $carga)
    {
        $this->form       = $form;
        $this->cotizacion = $cotizacion;
        $this->carga      = $carga;
    }

    public function build()
    {
        return $this->subject('Confirmación de Formulario de Comprobante - Consolidado #' . $this->carga)
            ->view('emails.comprobante_form_confirmation')
            ->with([
                'form'       => $this->form,
                'cotizacion' => $this->cotizacion,
                'carga'      => $this->carga,
            ]);
    }
}
