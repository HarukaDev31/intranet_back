<?php

namespace App\Support\WhatsApp;

use Illuminate\Support\Facades\Log;

/**
 * Resolución de teléfono de prueba (local / QA / FORCE) compartida por WhatsappTrait y Meta legacy.
 * El ambiente se define por APP_ENV / .env, no por dominio ni conexión de BD.
 */
class WhatsappEnvironmentPhone
{
    public static function shouldUseDefaultNumber(?string $domain = null): bool
    {
        if ((bool) env('FORCE_SEND_DEFAULT_NUMBER', false)) {
            return true;
        }

        $configured = env('WHATSAPP_USE_DEFAULT_NUMBER');
        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        return app()->environment(['local', 'testing', 'qa', 'staging']);
    }

    public static function resolve(?string $phoneNumberId, ?string $domain = null): ?string
    {
        if ($phoneNumberId === null || $phoneNumberId === '') {
            return $phoneNumberId;
        }

        if (!self::shouldUseDefaultNumber($domain)) {
            return $phoneNumberId;
        }

        $default = (string) env('DEFAULT_WHATSAPP_NUMBER', '51912705923@c.us');
        Log::info('WhatsApp: número por defecto (ambiente no productivo o forzado)', [
            'app_env' => app()->environment(),
            'original' => $phoneNumberId,
            'phoneNumberId' => $default,
        ]);

        return $default;
    }

    public static function inferDomain(): ?string
    {
        try {
            $request = request();
            if ($request) {
                $origin = $request->headers->get('origin');
                $referer = $request->headers->get('referer');
                $host = null;
                if ($origin) {
                    $host = parse_url($origin, PHP_URL_HOST);
                }
                if (!$host && $referer) {
                    $host = parse_url($referer, PHP_URL_HOST);
                }
                if ($host) {
                    return self::normalizeHost((string) $host);
                }

                $requestHost = $request->getHost();
                if (is_string($requestHost) && $requestHost !== '') {
                    return self::normalizeHost($requestHost);
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        $fromApp = parse_url((string) config('app.url', ''), PHP_URL_HOST);
        if (is_string($fromApp) && $fromApp !== '') {
            return self::normalizeHost($fromApp);
        }

        return null;
    }

    private static function normalizeHost(string $host): string
    {
        $host = explode(':', $host)[0];

        return (string) preg_replace('/^www\./', '', $host);
    }
}
