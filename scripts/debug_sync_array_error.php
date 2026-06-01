<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$baseUrl = 'https://probusiness.bitrix24.es/rest/23/dmhw4owllz0zt2rs/';
$cmd = app(App\Console\Commands\CopilotoSyncHistoricoCommand::class);
$ref = new ReflectionClass($cmd);

$fetch = $ref->getMethod('fetchAllRecentItems');
$fetch->setAccessible(true);
$filter = $ref->getMethod('filterLineChats');
$filter->setAccessible(true);
$extractChatId = $ref->getMethod('extractChatId');
$extractChatId->setAccessible(true);
$bitrixGet = $ref->getMethod('bitrixGet');
$bitrixGet->setAccessible(true);
$normalize = $ref->getMethod('normalizeBitrixMessage');
$normalize->setAccessible(true);
$skip = $ref->getMethod('shouldSkipExcludedChat');
$skip->setAccessible(true);
$phoneFromChat = $ref->getMethod('extractPhoneFromRecentChat');
$phoneFromChat->setAccessible(true);

$ventas = explode(',', env('BITRIX_LINEA_VENTAS_CC', '9240,25'));
$consolidado = explode(',', env('BITRIX_LINEA_CONSOLIDADO', '11784,33'));
$filterIds = array_merge($ventas, $consolidado);
$items = $fetch->invoke($cmd, $baseUrl);
$chats = $filter->invoke($cmd, $items, $filterIds, false);
echo 'filtered=' . count($chats) . PHP_EOL;

$seen = [];
foreach ($chats as $chat) {
    $chatId = $extractChatId->invoke($cmd, $chat);
    if (isset($seen[$chatId])) {
        continue;
    }
    $seen[$chatId] = true;
    $defaultPhone = $phoneFromChat->invoke($cmd, $chat);
    if ($skip->invoke($cmd, $chat, $defaultPhone)) {
        continue;
    }
    echo "Chat {$chatId} title=" . ($chat['title'] ?? '') . PHP_EOL;
    $lastId = null;
    for ($page = 0; $page < 10; $page++) {
        $query = ['DIALOG_ID' => 'chat' . $chatId, 'LIMIT' => 50];
        if ($lastId) {
            $query['LAST_ID'] = $lastId;
        }
        $payload = $bitrixGet->invoke($cmd, $baseUrl, 'im.dialog.messages.get', $query);
        $messages = $payload['result']['messages'] ?? [];
        if (!$messages) {
            break;
        }
        $users = $payload['result']['users'] ?? [];
    foreach ($messages as $message) {
        try {
            $normalized = $normalize->invoke($cmd, $message, $users, $ventas, $consolidado, $defaultPhone, 'ventas_cc');
            if (!$normalized) {
                continue;
            }
            $normalized['contact_name'] = $chat['title'] ?? null;
            app(App\Services\Copiloto\CopilotoMessageService::class)->persistMessage($normalized);
        } catch (\Throwable $e) {
            echo 'FAIL msg ' . ($message['id'] ?? '?') . ' chat ' . $chatId . ': ' . $e->getMessage() . PHP_EOL;
            echo json_encode($normalized ?? $message, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            exit(1);
        }
    }
        $last = end($messages);
        $lastId = $last['id'] ?? null;
        if (!$lastId) {
            break;
        }
    }
}
echo 'OK all chats' . PHP_EOL;
