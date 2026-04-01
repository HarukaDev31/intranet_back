<?php

namespace App\Services\Crm\Bitrix;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente mínimo REST Bitrix24 vía webhook entrante.
 * Reutilizable por varios adaptadores de embudo.
 */
class BitrixCrmClient
{
    /** @var string */
    private $webhookBaseUrl;

    public function __construct(string $webhookBaseUrl)
    {
        $this->webhookBaseUrl = $webhookBaseUrl;
    }

    public static function fromConfig(): ?self
    {
        $url = config('services.bitrix.webhook_url');
        if (empty($url) || !is_string($url)) {
            return null;
        }

        return new self(rtrim($url, '/') . '/');
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function call(string $method, array $body = []): array
    {
        $url = $this->webhookBaseUrl . ltrim($method, '/');
        $response = Http::timeout((int) config('services.bitrix.timeout', 30))
            ->acceptJson()
            ->asJson()
            ->post($url, $body);

        $json = $response->json() ?? [];

        if (!empty($json['error'])) {
            Log::warning('[Bitrix] API error', [
                'method' => $method,
                'error' => $json['error'] ?? null,
                'description' => $json['error_description'] ?? null,
            ]);
            throw new \RuntimeException(
                'Bitrix: ' . ($json['error_description'] ?? $json['error'] ?? 'unknown')
            );
        }

        return $json;
    }

    public function findContactIdByPhone(string $phoneE164): ?int
    {
        $json = $this->call('crm.duplicate.findbycomm', [
            'type' => 'PHONE',
            'values' => [$phoneE164],
            'entity_type' => 'CONTACT',
        ]);

        $ids = $json['result']['CONTACT'] ?? [];
        if (!is_array($ids) || $ids === []) {
            return null;
        }

        $first = $ids[0] ?? null;

        return is_numeric($first) ? (int) $first : null;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $params  p.ej. ['REGISTER_SONET_EVENT' => 'Y']
     */
    public function createContact(array $fields, array $params = [])
    {
        $body = ['fields' => $fields];
        if ($params !== []) {
            $body['params'] = $params;
        }
        $json = $this->call('crm.contact.add', $body);
        $id = $json['result'] ?? null;
        if (!is_numeric($id)) {
            throw new \RuntimeException('Bitrix: contact.add sin result');
        }

        return (int) $id;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $params
     */
    public function createDeal(array $fields, array $params = [])
    {
        $body = ['fields' => $fields];
        if ($params !== []) {
            $body['params'] = $params;
        }
        $json = $this->call('crm.deal.add', $body);
        $id = $json['result'] ?? null;
        if (!is_numeric($id)) {
            throw new \RuntimeException('Bitrix: deal.add sin result');
        }

        return (int) $id;
    }
}

