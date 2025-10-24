<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegisterConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $email;
    public $password;
    public $name;
    public $logo_header_white;
    public $logo_footer_white;
    public $social_icons;

    /**
     * Create a new message instance.
     */
    public function __construct($email, $password, $name, $logo_header_white, $logo_footer_white)
    {
        $this->email = $email;
        $this->password = $password;
        $this->name = $name;
        $this->logo_header_white = $logo_header_white;
        $this->logo_footer_white = $logo_footer_white;
        $this->social_icons = [
            'facebook' => public_path('storage/social_icons/facebook.png'),
            'instagram' => public_path('storage/social_icons/instagram.png'),
            'tiktok' => public_path('storage/social_icons/tiktok.png'),
            'youtube' => public_path('storage/social_icons/youtube.png'),
        ];
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Registro de usuario - ' . $this->name)
            ->view('emails.register')
            ->with([
                'email' => $this->email,
                'password' => $this->password,
                'logo_header' => $this->logo_header_white,
                'logo_footer' => $this->logo_footer_white,
            ]);
    }
}
