<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\ForgotPasswordMail;
use App\Support\BrandLogoPaths;

class SendForgotPasswordEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $token;
    protected $resetUrl;

    /**
     * Create a new job instance.
     *
     * @param string $email
     * @param string $token
     * @param string $resetUrl
     * @return void
     */
    public function __construct($email, $token, $resetUrl)
    {
        $this->email = $email;
        $this->token = $token;
        $this->resetUrl = $resetUrl;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $logoHeader = BrandLogoPaths::header();
            $logoFooter = BrandLogoPaths::footer();

            if ($logoHeader === null || $logoFooter === null) {
                Log::warning('Logos de marca no encontrados para forgot password', [
                    'logo_header' => $logoHeader,
                    'logo_footer' => $logoFooter,
                ]);
            }

            Mail::to($this->email)->send(new ForgotPasswordMail(
                $this->token,
                $this->email,
                $this->resetUrl,
                $logoHeader,
                $logoFooter
            ));

            Log::info('Correo de recuperación de contraseña enviado exitosamente', [
                'email' => $this->email
            ]);

        } catch (\Exception $e) {
            Log::error('Error en SendForgotPasswordEmailJob', [
                'email' => $this->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

