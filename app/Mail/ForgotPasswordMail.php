<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $email;
    public $resetUrl;
    public $logo_header;
    public $logo_footer;

    /**
     * Create a new message instance.
     */
    public function __construct($token, $email, $resetUrl, $logo_header, $logo_footer)
    {
        $this->token = $token;
        $this->email = $email;
        $this->resetUrl = $resetUrl;
        $this->logo_header = $logo_header;
        $this->logo_footer = $logo_footer;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Recupera tu contraseña - Probusiness')
            ->view('emails.forgot_password')
            ->with([
                'token' => $this->token,
                'email' => $this->email,
                'resetUrl' => $this->resetUrl,
                'logo_header' => $this->logo_header,
                'logo_footer' => $this->logo_footer,
            ]);
    }
}

