<?php

namespace App\Traits;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
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

            return $redirect;
        } catch (\Exception $e) {
            Log::warning('localMailTo: ' . $e->getMessage());

            return ($intended !== null && trim((string) $intended) !== '')
                ? trim((string) $intended)
                : null;
        }
    }

    /**
     * Redirige si APP_ENV=local, host localhost/127.0.0.1, o conexión mysql_local (jobs sin headers).
     */
    protected function shouldRedirectOutboundMail(): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        $domain = $this->resolveMailRequestDomain();
        if ($domain !== null && $domain !== '') {
            $d = strtolower($domain);
            if (in_array($d, ['localhost', '127.0.0.1'], true)) {
                return true;
            }
        }

        try {
            if (DB::getDefaultConnection() === 'mysql_local') {
                return true;
            }
        } catch (\Exception $e) {
            Log::debug('shouldRedirectOutboundMail BD: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Misma prioridad que WhatsappTrait::getRequestDomain: Origin/Referer, luego conexión BD.
     *
     * @return string|null Host normalizado (sin puerto) o dominio inferido de la conexión
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

            try {
                $currentConnection = DB::getDefaultConnection();
                $domain = $this->domainFromDbConnection($currentConnection);
                if ($domain) {
                    Log::info('MailTrait: dominio inferido desde conexión BD', [
                        'connection' => $currentConnection,
                        'domain' => $domain,
                    ]);

                    return $domain;
                }
            } catch (\Exception $dbException) {
                Log::debug('MailTrait: sin conexión BD: ' . $dbException->getMessage());
            }

            Log::debug('MailTrait: no se resolvió dominio (Origin/Referer ni BD)');

            return null;
        } catch (\Exception $e) {
            Log::warning('resolveMailRequestDomain: ' . $e->getMessage());

            return null;
        }
    }

    private function domainFromDbConnection(string $connection): ?string
    {
        $map = [
            'mysql' => 'intranetv2.probusiness.pe',
            'mysql_qa' => 'qaintranet.probusiness.pe',
            'mysql_local' => 'localhost',
        ];

        return $map[$connection] ?? null;
    }

    private function extractMailHost(string $host): string
    {
        $domain = explode(':', $host)[0];

        return preg_replace('/^www\./', '', $domain);
    }
}
