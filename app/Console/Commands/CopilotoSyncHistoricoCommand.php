<?php

namespace App\Console\Commands;

use App\Services\Copiloto\CopilotoMessageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CopilotoSyncHistoricoCommand extends Command
{
    protected $signature = 'copiloto:sync-historico
        {--force : Ejecuta aunque exista marca de sincronización}
        {--limit-chats=50 : Máximo de chats a procesar}
        {--limit-pages=10 : Máximo de páginas por chat}
        {--no-line-filter : No filtra por line IDs, procesa todos los chats tipo lines}';

    protected $aliases = ['copiloto:sync-historic'];

    protected $description = 'Sincroniza histórico de Bitrix Open Lines hacia copiloto_conversaciones y whatsapp_messages.';

    /** @var CopilotoMessageService */
    protected $copilotoMessageService;

    public function __construct(CopilotoMessageService $copilotoMessageService)
    {
        parent::__construct();
        $this->copilotoMessageService = $copilotoMessageService;
    }

    public function handle(): int
    {
        $alreadySyncedAt = Cache::get('copiloto:sync_historico:last_completed_at');
        if ($alreadySyncedAt && !$this->option('force')) {
            $this->warn('El sync histórico ya fue marcado como completado. Usa --force para reintentar.');
            return 0;
        }

        Cache::put('copiloto:sync_historico:status', [
            'state' => 'running',
            'started_at' => now()->toDateTimeString(),
            'message' => 'Sincronización histórica en curso',
        ], now()->addHours(4));

        $tokenIm = (string) env('BITRIX_WEBHOOK_TOKEN_IM', '');
        $webhook = (string) env('BITRIX_WEBHOOK_URL', '');
        $baseUrl = $this->resolveBitrixBaseUrl($tokenIm, $webhook);
        if ($baseUrl === '') {
            $message = 'Falta configurar BITRIX_WEBHOOK_TOKEN_IM o BITRIX_WEBHOOK_URL.';
            Cache::put('copiloto:sync_historico:status', [
                'state' => 'failed',
                'finished_at' => now()->toDateTimeString(),
                'message' => $message,
            ], now()->addHours(4));
            $this->error($message);
            return 1;
        }

        $limitChats = max(1, (int) $this->option('limit-chats'));
        $limitPages = max(1, (int) $this->option('limit-pages'));
        $lineIds = [
            (string) env('BITRIX_LINEA_VENTAS_CC', '9240'),
            (string) env('BITRIX_LINEA_CONSOLIDADO', '11784'),
        ];

        try {
            $this->info('Consultando chats de Bitrix...');
            $recent = $this->bitrixGet($baseUrl, 'im.recent.list');
            $items = $this->extractRecentItems($recent);
            $this->line('Chats recibidos desde Bitrix: ' . count($items));
            $chats = $this->filterLineChats($items, $lineIds, (bool) $this->option('no-line-filter'));
            $this->line('Chats filtrados para sync: ' . count($chats));
            $chats = array_slice($chats, 0, $limitChats);

            $processedChats = 0;
            $inserted = 0;
            $updated = 0;
            $skippedNoPhone = 0;
            $skippedNoMsgId = 0;

            foreach ($chats as $chat) {
                $chatId = $this->extractChatId($chat);
                if (!$chatId) {
                    continue;
                }

                $processedChats++;
                $defaultPhone = $this->extractPhoneFromRecentChat($chat);
                $contactName = $this->extractContactNameFromRecentChat($chat);
                $result = $this->syncChatMessages(
                    $baseUrl,
                    $chatId,
                    $limitPages,
                    $lineIds,
                    $defaultPhone,
                    $contactName
                );
                $inserted += $result['inserted'];
                $updated += $result['updated'];
                $skippedNoPhone += $result['skipped_no_phone'];
                $skippedNoMsgId += $result['skipped_no_message_id'];

                // Evita golpear rate limits de Bitrix durante el sync.
                usleep(500000);
            }

            $status = [
                'state' => 'completed',
                'started_at' => Cache::get('copiloto:sync_historico:status.started_at'),
                'finished_at' => now()->toDateTimeString(),
                'processed_chats' => $processedChats,
                'inserted_messages' => $inserted,
                'updated_messages' => $updated,
                'skipped_no_phone' => $skippedNoPhone,
                'skipped_no_message_id' => $skippedNoMsgId,
            ];
            Cache::put('copiloto:sync_historico:status', $status, now()->addHours(4));
            Cache::put('copiloto:sync_historico:last_completed_at', now()->toDateTimeString(), now()->addDays(30));

            $this->info(
                "Sync histórico completado. Chats: {$processedChats}, insertados: {$inserted}, actualizados: {$updated}, "
                . "omitidos_sin_phone: {$skippedNoPhone}, omitidos_sin_msg_id: {$skippedNoMsgId}"
            );
            Log::info('[Copiloto][SyncHistorico] Completado', $status);
        } catch (\Throwable $e) {
            Cache::put('copiloto:sync_historico:status', [
                'state' => 'failed',
                'finished_at' => now()->toDateTimeString(),
                'message' => $e->getMessage(),
            ], now()->addHours(4));
            Log::error('[Copiloto][SyncHistorico] Error', ['error' => $e->getMessage()]);
            $this->error('Error en sync histórico: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function resolveBitrixBaseUrl($tokenIm, $webhook)
    {
        if ($tokenIm !== '') {
            return 'https://probusiness.bitrix24.es/rest/23/' . trim($tokenIm, '/') . '/';
        }
        if ($webhook !== '') {
            return rtrim($webhook, '/') . '/';
        }
        return '';
    }

    private function bitrixGet($baseUrl, $method, array $query = [])
    {
        $url = rtrim($baseUrl, '/') . '/' . $method . '.json';
        $response = Http::timeout(30)->get($url, $query);
        if (!$response->successful()) {
            throw new \RuntimeException("Bitrix {$method} respondió HTTP {$response->status()}");
        }
        return (array) $response->json();
    }

    private function extractRecentItems(array $recent)
    {
        $items = data_get($recent, 'result.items');
        if (is_array($items) && count($items) > 0) {
            return $items;
        }

        $result = data_get($recent, 'result');
        if (is_array($result)) {
            // Algunas respuestas devuelven directamente el array de chats en result.
            if ($this->isListArray($result)) {
                return $result;
            }

            // En otros formatos viene anidado con otra clave.
            foreach ($result as $value) {
                if (is_array($value) && $this->isListArray($value)) {
                    return $value;
                }
            }
        }

        return [];
    }

    private function filterLineChats(array $items, array $lineIds, $noLineFilter = false)
    {
        $out = [];
        foreach ($items as $item) {
            $type = (string) data_get($item, 'type', '');
            $entityId = (string) data_get($item, 'entity_id', '');
            if ($entityId === '') {
                $entityId = (string) data_get($item, 'entityId', '');
            }
            if ($entityId === '') {
                $entityId = (string) data_get($item, 'params.ENTITY_ID', '');
            }

            // En ciertos escenarios type llega vacío; si hay chat + entity_id de conector, se considera lines.
            $looksLikeLine = ($type === 'lines')
                || (strpos($entityId, 'bitrix_whatcrm_net_') !== false)
                || (strpos((string) data_get($item, 'id', ''), 'chat') === 0);

            if (!$looksLikeLine) {
                continue;
            }
            if ($noLineFilter) {
                $out[] = $item;
                continue;
            }
            $ok = false;
            foreach ($lineIds as $lineId) {
                if ($lineId !== '' && (strpos($entityId, '|' . $lineId) !== false || strpos($entityId, (string) $lineId) !== false)) {
                    $ok = true;
                    break;
                }
            }
            if ($ok) {
                $out[] = $item;
            }
        }
        return $out;
    }

    private function extractChatId(array $chat)
    {
        $dialogId = (string) data_get($chat, 'id', '');
        if ($dialogId === '') {
            $dialogId = (string) data_get($chat, 'dialog_id', '');
        }
        if ($dialogId === '') {
            $dialogId = (string) data_get($chat, 'chat_id', '');
        }
        if (strpos($dialogId, 'chat') === 0) {
            return (int) str_replace('chat', '', $dialogId);
        }
        return is_numeric($dialogId) ? (int) $dialogId : null;
    }

    private function syncChatMessages($baseUrl, $chatId, $limitPages, array $lineIds, $defaultPhone = null, $contactName = null)
    {
        $inserted = 0;
        $updated = 0;
        $skippedNoPhone = 0;
        $skippedNoMessageId = 0;
        $lastId = null;
        $page = 0;

        while ($page < $limitPages) {
            $query = [
                'DIALOG_ID' => 'chat' . $chatId,
                'LIMIT' => 50,
            ];
            if ($lastId) {
                $query['LAST_ID'] = $lastId;
            }

            $payload = $this->bitrixGet($baseUrl, 'im.dialog.messages.get', $query);
            $messages = (array) data_get($payload, 'result.messages', []);
            $users = (array) data_get($payload, 'result.users', []);
            if (count($messages) === 0) {
                break;
            }

            foreach ($messages as $message) {
                $normalized = $this->normalizeBitrixMessage($message, $users, $lineIds, $defaultPhone);
                if (!$normalized) {
                    $skippedNoMessageId++;
                    continue;
                }
                if (!$normalized['phone']) {
                    $skippedNoPhone++;
                    continue;
                }

                if ($contactName) {
                    $normalized['contact_name'] = $contactName;
                }

                $persisted = $this->copilotoMessageService->persistMessage($normalized);
                if ($persisted['created']) {
                    $inserted++;
                } else {
                    $updated++;
                }
            }

            $last = end($messages);
            $lastId = data_get($last, 'id');
            if (!$lastId) {
                break;
            }
            $page++;
            usleep(500000);
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped_no_phone' => $skippedNoPhone,
            'skipped_no_message_id' => $skippedNoMessageId,
        ];
    }

    private function normalizeBitrixMessage(array $message, array $users, array $lineIds, $defaultPhone = null)
    {
        $connectorMid = (string) data_get($message, 'params.CONNECTOR_MID', '');
        $direction = (strpos($connectorMid, 'true_') === 0) ? 'out' : 'in';
        $bitrixMsgId = (string) data_get($message, 'id', '');
        if ($bitrixMsgId === '') {
            return null;
        }

        $userId = data_get($message, 'author_id');
        $phone = $this->extractPhoneFromUsers($users, $userId);
        if (!$phone) {
            $phone = $this->extractPhoneFromMessage($message);
        }
        if (!$phone && $defaultPhone) {
            $phone = $this->normalizePhone($defaultPhone);
        }
        $linea = $this->resolveLineaFromMessage($message, $lineIds);

        $text = (string) (data_get($message, 'text') ?: '');
        $text = $this->cleanText($text);

        $date = data_get($message, 'date');
        $sentAt = is_numeric($date) ? date('Y-m-d H:i:s', (int) $date) : now()->toDateTimeString();

        return [
            'phone' => $phone,
            'bitrix_chat_id' => (string) data_get($message, 'chat_id', ''),
            'bitrix_msg_id' => $bitrixMsgId,
            'direction' => $direction,
            'body' => $text,
            'source' => 'bitrix',
            'linea' => $linea,
            'sent_at' => $sentAt,
        ];
    }

    private function extractContactNameFromRecentChat(array $chat)
    {
        $name = data_get($chat, 'counterparty.name')
            ?: data_get($chat, 'user.name')
            ?: data_get($chat, 'title');
        if (!$name) {
            return null;
        }
        $text = trim((string) $name);
        return $text === '' ? null : $text;
    }

    private function extractPhoneFromUsers(array $users, $userId)
    {
        if (!$userId) {
            return null;
        }

        $user = data_get($users, $userId);
        if (!$user) {
            foreach ($users as $candidate) {
                if ((string) data_get($candidate, 'id') === (string) $userId) {
                    $user = $candidate;
                    break;
                }
            }
        }

        $phone = data_get($user, 'phones.personal_mobile')
            ?: data_get($user, 'personal_mobile')
            ?: data_get($user, 'phone');
        if (!$phone) {
            return null;
        }

        $clean = preg_replace('/\D+/', '', (string) $phone);
        return $clean !== '' ? $clean : null;
    }

    private function extractPhoneFromMessage(array $message)
    {
        $candidates = [
            data_get($message, 'params.CONNECTOR_MID'),
            data_get($message, 'params.ENTITY_DATA_1'),
            data_get($message, 'params.ENTITY_ID'),
            data_get($message, 'chat_id'),
            data_get($message, 'dialog_id'),
            data_get($message, 'text'),
        ];

        foreach ($candidates as $candidate) {
            $phone = $this->extractFirstPhoneLike($candidate);
            if ($phone) {
                return $phone;
            }
        }

        return null;
    }

    private function resolveLineaFromMessage(array $message, array $lineIds)
    {
        $raw = (string) data_get($message, 'params.ENTITY_DATA_1', '');
        if ($raw === '') {
            $raw = (string) data_get($message, 'params.ENTITY_ID', '');
        }

        if ($lineIds[0] !== '' && strpos($raw, (string) $lineIds[0]) !== false) {
            return 'ventas_cc';
        }
        if ($lineIds[1] !== '' && strpos($raw, (string) $lineIds[1]) !== false) {
            return 'consolidado';
        }
        return null;
    }

    private function cleanText($text)
    {
        $clean = preg_replace('/\[.*?\]/', '', (string) $text);
        return trim((string) $clean);
    }

    private function extractPhoneFromRecentChat(array $chat)
    {
        $candidates = [
            data_get($chat, 'counterparty.phone'),
            data_get($chat, 'counterparty.personal_mobile'),
            data_get($chat, 'user.phone'),
            data_get($chat, 'user.personal_mobile'),
            data_get($chat, 'params.PHONE'),
            data_get($chat, 'entity_id'),
            data_get($chat, 'message.text'),
        ];

        foreach ($candidates as $candidate) {
            $phone = $this->extractFirstPhoneLike($candidate);
            if ($phone) {
                return $phone;
            }
        }

        return null;
    }

    private function extractFirstPhoneLike($raw)
    {
        if ($raw === null) {
            return null;
        }
        $text = (string) $raw;
        if ($text === '') {
            return null;
        }

        if (preg_match('/(51\d{9,11})/', $text, $m)) {
            return $this->normalizePhone($m[1]);
        }
        if (preg_match('/\b(9\d{8})\b/', $text, $m)) {
            return $this->normalizePhone($m[1]);
        }
        if (preg_match('/\b(\d{10,13})\b/', $text, $m)) {
            return $this->normalizePhone($m[1]);
        }

        return null;
    }

    private function normalizePhone($raw)
    {
        $clean = preg_replace('/\D+/', '', (string) $raw);
        if ($clean === '') {
            return null;
        }
        if (strlen($clean) === 9 && strpos($clean, '9') === 0) {
            return '51' . $clean;
        }
        return $clean;
    }

    private function isListArray(array $value)
    {
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }
}

