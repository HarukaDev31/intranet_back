<?php

namespace App\Support\WhatsApp;

use Illuminate\Support\Facades\Log;

/**
 * Logs unificados del módulo WhatsApp Inbox (buscar "[WaInbox]" en storage/logs).
 */
class WaInboxLog
{
    /**
     * @param  string  $event
     * @param  array<string, mixed>  $context
     */
    public static function info($event, array $context = [])
    {
        Log::info('[WaInbox] ' . $event, self::enrich($context));
    }

    /**
     * @param  string  $event
     * @param  array<string, mixed>  $context
     */
    public static function warning($event, array $context = [])
    {
        Log::warning('[WaInbox] ' . $event, self::enrich($context));
    }

    /**
     * @param  string  $event
     * @param  array<string, mixed>  $context
     */
    public static function error($event, array $context = [])
    {
        Log::error('[WaInbox] ' . $event, self::enrich($context));
    }

    /**
     * Oculta tokens y trunca URLs largas en payloads para logs.
     *
     * @param  mixed  $payload
     * @return mixed
     */
    public static function sanitizePayload($payload)
    {
        if (!is_array($payload)) {
            return $payload;
        }

        $out = [];
        foreach ($payload as $key => $value) {
            $k = is_string($key) ? strtolower($key) : $key;
            if (in_array($k, ['access_token', 'authorization', 'password', 'secret'], true)) {
                $out[$key] = '[redacted]';
                continue;
            }
            if (is_array($value)) {
                $out[$key] = self::sanitizePayload($value);
                continue;
            }
            if (is_string($value) && (strpos($k, 'link') !== false || strpos($k, 'url') !== false) && strlen($value) > 120) {
                $out[$key] = substr($value, 0, 80) . '…[truncated]';
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function enrich(array $context)
    {
        return array_merge([
            'coordinacion_enabled' => (bool) config('meta_whatsapp.coordinacion_enabled'),
            'inbox_queue' => (string) config('meta_whatsapp.inbox_queue', 'notificaciones'),
            'phone_number_id_set' => (string) config('meta_whatsapp.phone_number_id') !== '',
            'access_token_set' => (string) config('meta_whatsapp.access_token') !== '',
        ], $context);
    }
}
