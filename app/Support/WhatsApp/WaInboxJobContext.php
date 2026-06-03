<?php

namespace App\Support\WhatsApp;

/**
 * Dominio de BD para jobs WaInbox (cola separada del HTTP que pasó por middleware).
 */
class WaInboxJobContext
{
    public static function resolveJobDomain(?string $domain = null): string
    {
        if ($domain !== null && trim($domain) !== '') {
            return trim($domain);
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
                        $host = explode(':', $host)[0];
                        $host = preg_replace('/^www\./', '', $host);

                        return $host;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Sin request (CLI / worker)
        }

        return (string) config('meta_whatsapp.inbox_job_domain', 'localhost');
    }
}
