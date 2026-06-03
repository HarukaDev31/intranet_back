<?php

namespace App\Support\WhatsApp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Resolución de teléfono de prueba (localhost / QA / FORCE) compartida por WhatsappTrait y Meta legacy.
 */
class WhatsappEnvironmentPhone
{
    /** @var array<int, string> */
    private const DOMAINS_DEFAULT = ['localhost', '127.0.0.1', 'qaintranet.probusiness.pe'];

    /** @var array<string, string> */
    private const CONNECTION_DOMAIN_MAP = [
        'mysql' => 'intranetv2.probusiness.pe',
        'mysql_qa' => 'qaintranet.probusiness.pe',
        'mysql_local' => 'localhost',
    ];

    public static function shouldUseDefaultNumber(?string $domain = null): bool
    {
        $domain = $domain ?? self::inferDomain();

        if ($domain !== null) {
            foreach (self::DOMAINS_DEFAULT as $allowed) {
                if (strpos($domain, $allowed) !== false || $domain === $allowed) {
                    return true;
                }
            }
        }

        return (bool) env('FORCE_SEND_DEFAULT_NUMBER', false);
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
        Log::info('WhatsApp: número por defecto (ambiente dev/QA o forzado)', [
            'domain' => $domain ?? self::inferDomain(),
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
            }

            $connection = DB::getDefaultConnection();

            return self::CONNECTION_DOMAIN_MAP[$connection] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function normalizeHost(string $host): string
    {
        $host = explode(':', $host)[0];

        return (string) preg_replace('/^www\./', '', $host);
    }
}
