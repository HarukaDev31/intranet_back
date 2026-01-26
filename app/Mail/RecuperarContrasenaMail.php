<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecuperarContrasenaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $nombre;
    public $recuperarContrasenaUrl;
    public $logo_header;
    public $logo_footer;

    /**
     * Create a new message instance.
     */
    public function __construct($nombre, $recuperarContrasenaUrl, $logo_header, $logo_footer)
    {
        $this->nombre = $nombre;
        $this->recuperarContrasenaUrl = $recuperarContrasenaUrl;
        $this->logo_header = $logo_header;
        $this->logo_footer = $logo_footer;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Recupera tu contraseña - Probusiness')
            ->view('emails.recuperar_contrasena')
            ->with([
                'nombre' => $this->nombre,
                'recuperarContrasenaUrl' => $this->recuperarContrasenaUrl,
                'logo_header' => $this->logo_header,
                'logo_footer' => $this->logo_footer,
            ]);
    }
}

