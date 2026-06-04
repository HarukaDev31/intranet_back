<?php

namespace App\Support\WhatsApp;

use Illuminate\Support\Facades\Log;

/**
 * Logs unificados del módulo WhatsApp Copiloto (buscar "[WaCopiloto]" en storage/logs).
 */
class WaCopilotoLog
{
    /**
     * @param  string  $event
     * @param  array<string, mixed>  $context
     */
    public static function info($event, array $context = [])
    {
        Log::info('[WaCopiloto] ' . $event, self::enrich($context));
    }

    /**
     * @param  string  $event
     * @param  array<string, mixed>  $context
     */
    public static function warning($event, array $context = [])
    {
        Log::warning('[WaCopiloto] ' . $event, self::enrich($context));
    }

    /**
     * @param  string  $event
     * @param  array<string, mixed>  $context
     */
    public static function error($event, array $context = [])
    {
        Log::error('[WaCopiloto] ' . $event, self::enrich($context));
    }

    /**
     * @param  mixed  $payload
     * @return mixed
     */
    public static function sanitizePayload($payload)
    {
        if (!is_array($payload)) {
            return $payload;
        }

        $copy = $payload;
        foreach (['access_token', 'token', 'authorization'] as $key) {
            if (isset($copy[$key])) {
                $copy[$key] = '[redacted]';
            }
        }

        return $copy;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function enrich(array $context)
    {
        return $context;
    }
}
