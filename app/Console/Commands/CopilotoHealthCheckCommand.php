<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CopilotoHealthCheckCommand extends Command
{
    protected $signature = 'copiloto:health-check';

    protected $description = 'Valida estado de Evolution y Bitrix para Copiloto WhatsApp';

    public function handle(): int
    {
        $this->checkEvolution();
        $this->checkBitrix();

        return 0;
    }

    private function checkEvolution(): void
    {
        $baseUrl = rtrim((string) env('EVOLUTION_URL', ''), '/');
        $instance = (string) env('EVOLUTION_INSTANCE', '');
        $apiKey = (string) env('EVOLUTION_API_KEY', '');

        if ($baseUrl === '' || $instance === '') {
            Cache::put('copiloto:evolution:healthy', false, now()->addMinutes(2));
            Log::warning('[Copiloto][Health] Evolution no configurado.');
            return;
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $apiKey,
            ])->timeout(5)->get($baseUrl . '/instance/connectionState/' . $instance);

            $state = data_get($response->json(), 'instance.state')
                ?: data_get($response->json(), 'state');

            if ($response->successful() && $state === 'open') {
                Cache::put('copiloto:evolution:healthy', true, now()->addMinutes(2));
                return;
            }

            Http::withHeaders([
                'apikey' => $apiKey,
            ])->timeout(5)->get($baseUrl . '/instance/connect/' . $instance);

            Cache::put('copiloto:evolution:healthy', false, now()->addMinutes(2));
            Log::warning('[Copiloto][Health] Evolution reconexión solicitada.', [
                'state' => $state,
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Cache::put('copiloto:evolution:healthy', false, now()->addMinutes(2));
            Log::error('[Copiloto][Health] Error Evolution', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function checkBitrix(): void
    {
        $url = rtrim((string) env('BITRIX_WEBHOOK_URL', ''), '/');
        if ($url === '') {
            Cache::put('copiloto:bitrix:healthy', false, now()->addMinutes(2));
            return;
        }

        try {
            $response = Http::timeout(5)->get($url . '/server.time.json');
            Cache::put('copiloto:bitrix:healthy', $response->successful(), now()->addMinutes(2));
        } catch (\Throwable $e) {
            Cache::put('copiloto:bitrix:healthy', false, now()->addMinutes(2));
            Log::error('[Copiloto][Health] Error Bitrix', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

