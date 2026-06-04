<?php

namespace App\Support\WhatsApp;

use App\Jobs\WaCopiloto\ProcessWaCopilotoInboundJob;
use App\Jobs\WhatsappInbox\ProcessWaInboxInboundJob;
use App\Models\WaCopiloto\WaCopilotoWebhookLog;
use App\Models\WhatsappInbox\WaInboxWebhookLog;
use App\Services\WaCopiloto\WaCopilotoSessionService;
use App\Services\WhatsappInbox\WhatsappInboxSessionService;

/**
 * Enruta webhooks Meta al módulo inbox o copiloto según phone_number_id.
 */
class MetaWhatsappWebhookRouter
{
    /**
     * @param  array<string, mixed>  $payload
     * @return string inbox|copiloto|unknown
     */
    public static function resolveProduct(array $payload)
    {
        $phoneNumberIds = self::extractPhoneNumberIds($payload);
        if (!$phoneNumberIds) {
            return 'unknown';
        }

        /** @var WhatsappInboxSessionService $inboxSessions */
        $inboxSessions = app(WhatsappInboxSessionService::class);
        /** @var WaCopilotoSessionService $copilotoSessions */
        $copilotoSessions = app(WaCopilotoSessionService::class);

        $inboxMatch = false;
        $copilotoMatch = false;

        foreach ($phoneNumberIds as $phoneNumberId) {
            if ($inboxSessions->findByPhoneNumberId($phoneNumberId)) {
                $inboxMatch = true;
            }
            if ($copilotoSessions->findByPhoneNumberId($phoneNumberId)) {
                $copilotoMatch = true;
            }
        }

        if ($copilotoMatch && !$inboxMatch) {
            return 'copiloto';
        }

        if ($inboxMatch) {
            return 'inbox';
        }

        if ($copilotoMatch) {
            return 'copiloto';
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string|null  $domain
     * @return void
     */
    public static function dispatch(array $payload, $domain = null)
    {
        $product = self::resolveProduct($payload);

        if ($product === 'copiloto') {
            $log = WaCopilotoWebhookLog::create([
                'payload' => $payload,
                'processed_at' => null,
            ]);
            ProcessWaCopilotoInboundJob::dispatch($log->id, WaCopilotoJobContext::resolveJobDomain($domain));

            return;
        }

        $log = WaInboxWebhookLog::create([
            'payload' => $payload,
            'processed_at' => null,
        ]);
        ProcessWaInboxInboundJob::dispatch($log->id, WaInboxJobContext::resolveJobDomain($domain));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private static function extractPhoneNumberIds(array $payload)
    {
        $ids = [];
        $entries = isset($payload['entry']) && is_array($payload['entry']) ? $payload['entry'] : [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $changes = isset($entry['changes']) && is_array($entry['changes']) ? $entry['changes'] : [];
            foreach ($changes as $change) {
                if (!is_array($change)) {
                    continue;
                }
                $value = isset($change['value']) && is_array($change['value']) ? $change['value'] : [];
                if (isset($value['metadata']['phone_number_id'])) {
                    $ids[] = (string) $value['metadata']['phone_number_id'];
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
