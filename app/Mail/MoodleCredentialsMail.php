<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable para enviar las credenciales de acceso a Moodle
 * 
 * Esta clase se encarga de enviar un correo electrónico con las credenciales
 * de acceso a la plataforma Moodle cuando se crea o actualiza un usuario.
 */
class MoodleCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $username;
    public $password;
    public $email;
    public $nombre;
    public $moodleUrl;
    public $logo_header;
    public $logo_footer;

    /**
     * Create a new message instance.
     *
     * @param string $username Usuario de Moodle
     * @param string $password Contraseña de Moodle
     * @param string $email Email del usuario
     * @param string $nombre Nombre completo del usuario
     * @param string $moodleUrl URL de la plataforma Moodle
     * @param string $logo_header Ruta del logo para el header
     * @param string $logo_footer Ruta del logo para el footer
     */
    public function __construct($username, $password, $email, $nombre, $moodleUrl, $logo_header, $logo_footer)
    {
        $this->username = $username;
        $this->password = $password;
        $this->email = $email;
        $this->nombre = $nombre;
        $this->moodleUrl = $moodleUrl;
        $this->logo_header = $logo_header;
        $this->logo_footer = $logo_footer;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Credenciales de acceso a Moodle - Probusiness')
            ->view('emails.moodle_credentials')
            ->with([
                'username' => $this->username,
                'password' => $this->password,
                'email' => $this->email,
                'nombre' => $this->nombre,
                'moodleUrl' => $this->moodleUrl,
                'logo_header' => $this->logo_header,
                'logo_footer' => $this->logo_footer,
            ]);
    }
}

