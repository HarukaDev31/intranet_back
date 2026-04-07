<?php

namespace App\Services\Crm\Bitrix;

use Illuminate\Http\Client\ConnectionException;
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
        $timeout = (int) config('services.bitrix.timeout', 30);
        $connectTimeout = (int) config('services.bitrix.connect_timeout', 10);
        $retryTimes = (int) config('services.bitrix.retry_times', 2);
        $retrySleepMs = (int) config('services.bitrix.retry_sleep_ms', 800);

        $response = Http::timeout($timeout)
            ->withOptions(['connect_timeout' => $connectTimeout])
            ->retry($retryTimes, $retrySleepMs, function ($exception) {
                return $exception instanceof ConnectionException;
            })
            ->acceptJson()
            ->asJson()
            ->post($url, $body);

        if (!$response->successful()) {
            $rawBody = (string) $response->body();
            Log::warning('[Bitrix] HTTP error', [
                'method' => $method,
                'status' => $response->status(),
                'body' => mb_substr($rawBody, 0, 2000),
            ]);

            throw new \RuntimeException(
                'Bitrix HTTP ' . $response->status() . ' en ' . $method
            );
        }

        $json = $response->json() ?? [];
        if (!is_array($json)) {
            $rawBody = (string) $response->body();
            Log::warning('[Bitrix] Respuesta no JSON válida', [
                'method' => $method,
                'body' => mb_substr($rawBody, 0, 2000),
            ]);

            throw new \RuntimeException('Bitrix: respuesta inválida en ' . $method);
        }

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
        $id = $this->extractNumericResult($json);
        if ($id === null) {
            Log::warning('[Bitrix] contact.add sin result numérico', [
                'result' => $json['result'] ?? null,
            ]);
            throw new \RuntimeException('Bitrix: contact.add sin result');
        }

        return $id;
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
        $id = $this->extractNumericResult($json);
        if ($id === null) {
            Log::warning('[Bitrix] deal.add sin result numérico', [
                'result' => $json['result'] ?? null,
            ]);
            throw new \RuntimeException('Bitrix: deal.add sin result');
        }

        return $id;
    }

    /**
     * Bitrix a veces devuelve result escalar, y en algunos métodos puede venir
     * como array con ID/id. Normalizamos ambos formatos.
     *
     * @param  array<string, mixed>  $json
     */
    private function extractNumericResult(array $json): ?int
    {
        $result = $json['result'] ?? null;
        if (is_numeric($result)) {
            return (int) $result;
        }

        if (is_array($result)) {
            $candidates = [
                $result['ID'] ?? null,
                $result['id'] ?? null,
            ];
            foreach ($candidates as $candidate) {
                if (is_numeric($candidate)) {
                    return (int) $candidate;
                }
            }
        }

        return null;
    }
}

