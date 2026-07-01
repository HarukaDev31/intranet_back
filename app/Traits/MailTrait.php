<?php

namespace App\Traits;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

trait MailTrait
{
    /**
     * Envía un Mailable; si aplica redirección (dominio/entorno dev), usa MAIL_LOCAL_REDIRECT_TO.
     */
    protected function sendMailTo(?string $intended, Mailable $mailable): void
    {
        $to = $this->localMailTo($intended);
        if ($to !== null && $to !== '') {
            Mail::to($to)->send($mailable);
        }
    }

    /**
     * Destinatarios de coordinación (COORDINATION_EMAIL y COORDINATION_EMAIL_2).
     *
     * @return string[]
     */
    protected function coordinationEmails(): array
    {
        $emails = [];

        foreach (['COORDINATION_EMAIL', 'COORDINATION_EMAIL_2'] as $key) {
            $email = trim((string) env($key, ''));
            if ($email !== '' && !in_array($email, $emails, true)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * Envía a todos los correos de coordinación configurados en un solo envío.
     */
    protected function sendMailToCoordination(Mailable $mailable): void
    {
        $recipients = [];

        foreach ($this->coordinationEmails() as $intended) {
            $to = $this->localMailTo($intended);
            if ($to !== null && $to !== '' && !in_array($to, $recipients, true)) {
                $recipients[] = $to;
            }
        }

        if ($recipients === []) {
            return;
        }

        Mail::to($recipients)->send($mailable);
    }

    /**
     * Destinatario efectivo según dominio (Origin/Referer o BD, como WhatsappTrait) y entorno.
     */
    protected function localMailTo(?string $intended): ?string
    {
        try {
            if ($intended === null || trim($intended) === '') {
                return null;
            }

            $intended = trim($intended);

            $redirect = trim((string) config('mail.local_redirect_to', ''));
            if ($redirect === '' || !$this->shouldRedirectOutboundMail()) {
                return $intended;
            }

            Log::info('Correo redirigido (dominio/entorno no productivo)', [
                'intended' => $intended,
                'redirect' => $redirect,
                'domain' => $this->resolveMailRequestDomain(),
            ]);
            if (env('FORCE_SEND_DEFAULT_MAIL', false)) {
                return env('MAIL_LOCAL_REDIRECT_TO');
            }
            return $redirect;
        } catch (\Exception $e) {
            Log::warning('localMailTo: ' . $e->getMessage());

            return ($intended !== null && trim((string) $intended) !== '')
                ? trim((string) $intended)
                : null;
        }
    }

    /**
     * Redirige correo en local/QA si MAIL_LOCAL_REDIRECT_TO está definido.
     */
    protected function shouldRedirectOutboundMail(): bool
    {
        if (!app()->environment(['local', 'testing', 'qa', 'staging'])) {
            return false;
        }

        return trim((string) env('MAIL_LOCAL_REDIRECT_TO', '')) !== '';
    }

    /**
     * Origin/Referer o host de APP_URL (solo metadatos / redirección de correo).
     *
     * @return string|null Host normalizado (sin puerto)
     */
    protected function resolveMailRequestDomain(): ?string
    {
        try {
            $request = request();
            if ($request) {
                $origin = $request->headers->get('origin');
                $referer = $request->headers->get('referer');

                $sourceHost = null;
                if ($origin) {
                    $sourceHost = parse_url($origin, PHP_URL_HOST);
                }
                if (!$sourceHost && $referer) {
                    $sourceHost = parse_url($referer, PHP_URL_HOST);
                }

                if ($sourceHost) {
                    return $this->extractMailHost($sourceHost);
                }
            }

            $fromApp = parse_url((string) config('app.url', ''), PHP_URL_HOST);
            if (is_string($fromApp) && $fromApp !== '') {
                return $this->extractMailHost($fromApp);
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('resolveMailRequestDomain: ' . $e->getMessage());

            return null;
        }
    }

    private function extractMailHost(string $host): string
    {
        $domain = explode(':', $host)[0];

        return preg_replace('/^www\./', '', $domain);
    }
}
