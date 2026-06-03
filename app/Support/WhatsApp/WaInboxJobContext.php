<?php

namespace App\Support\WhatsApp;

/**
 * Dominio de BD para jobs WaInbox (cola sin Origin/Referer del front, p. ej. webhook Meta).
 * Debe alinearse con DatabaseSelectionMiddleware / DatabaseConnectionTrait.
 */
class WaInboxJobContext
{
    public static function resolveJobDomain(?string $domain = null): string
    {
        if ($domain !== null && trim($domain) !== '') {
            return self::normalizeHost($domain);
        }

        try {
            $request = request();
            if ($request) {
                foreach (['origin', 'referer'] as $headerName) {
                    $header = $request->headers->get($headerName);
                    if (!$header) {
                        continue;
                    }
                    $host = parse_url($header, PHP_URL_HOST);
                    if (is_string($host) && $host !== '') {
                        return self::normalizeHost($host);
                    }
                }

                // Webhook Meta: sin Origin; usar host del API (igual que DatabaseSelectionMiddleware).
                $requestHost = $request->getHost();
                if (is_string($requestHost) && $requestHost !== '') {
                    return self::normalizeHost($requestHost);
                }
            }
        } catch (\Throwable $e) {
            // Worker CLI sin request HTTP
        }

        $fromApp = self::hostFromUrl((string) config('app.url', ''));
        if ($fromApp !== '') {
            return $fromApp;
        }

        $inbox = trim((string) config('meta_whatsapp.inbox_job_domain', ''));
        if ($inbox !== '' && $inbox !== 'localhost') {
            return self::normalizeHost($inbox);
        }

        $queue = trim((string) config('database.queue_job_domain', ''));
        if ($queue !== '' && $queue !== 'localhost') {
            return self::normalizeHost($queue);
        }

        return app()->environment('local') ? 'localhost' : 'intranetv2.probusiness.pe';
    }

    private static function normalizeHost($host): string
    {
        $host = explode(':', trim((string) $host))[0];
        $host = preg_replace('/^www\./', '', $host);

        return $host !== '' ? $host : 'localhost';
    }

    private static function hostFromUrl($url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }

        return self::normalizeHost($host);
    }
}
