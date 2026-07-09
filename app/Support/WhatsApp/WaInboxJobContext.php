<?php

namespace App\Support\WhatsApp;

/**
 * Metadatos de contexto para jobs WaInbox (auditoría / job_domain en batches).
 * La BD ya no se elige por dominio: cada despliegue usa su .env.
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

        return 'localhost';
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
