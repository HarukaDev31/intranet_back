<?php

namespace App\Mail;

use App\Models\Fabricante\PUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FabricanteEmailVerificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public PUser $user,
        public string $verificationUrl,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Verifica tu correo — ProBusiness Fabricante')
            ->view('emails.fabricante_verify_email')
            ->with([
                'companyName' => $this->user->company_name,
                'verificationUrl' => $this->verificationUrl,
            ]);
    }
}
