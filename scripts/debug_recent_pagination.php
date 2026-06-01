<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$base = 'https://probusiness.bitrix24.es/rest/23/dmhw4owllz0zt2rs/';
$filterIds = ['9240', '25', '11784', '33'];
$all = [];
$offset = 0;
$limit = 200;
$pages = 0;

do {
    $r = Illuminate\Support\Facades\Http::timeout(30)->get($base . 'im.recent.list.json', [
        'OFFSET' => $offset,
        'LIMIT' => $limit,
        'SKIP_OPENLINES' => 'N',
    ]);
    $json = $r->json();
    $batch = $json['result']['items'] ?? $json['result'] ?? [];
    if (!is_array($batch)) {
        $batch = [];
    }
    foreach ($batch as $item) {
        $all[] = $item;
    }
    $hasMore = (bool) ($json['result']['hasMore'] ?? false);
    $offset += $limit;
    $pages++;
    echo "page $pages offset=$offset batch=" . count($batch) . " total=" . count($all) . " hasMore=" . ($hasMore ? 'Y' : 'N') . PHP_EOL;
} while ($hasMore && $pages < 50);

$uniqueLines = [];
foreach ($all as $item) {
    if ((string) ($item['chat']['entity_type'] ?? '') !== 'LINES') {
        continue;
    }
    $entityId = (string) ($item['chat']['entity_id'] ?? '');
    $ok = false;
    foreach ($filterIds as $lineId) {
        if ($lineId !== '' && (strpos($entityId, '|' . $lineId . '|') !== false || strpos($entityId, $lineId) !== false)) {
            $ok = true;
            break;
        }
    }
    if (!$ok) {
        continue;
    }
    $chatId = $item['chat_id'] ?? null;
    if (!$chatId && isset($item['id']) && strpos((string) $item['id'], 'chat') === 0) {
        $chatId = (int) str_replace('chat', '', (string) $item['id']);
    }
    if (!$chatId) {
        continue;
    }
    if (preg_match('/chat\.(\d{12,})/', $entityId)) {
        continue; // group
    }
    if (preg_match('/chat\.(51\d{9})/', $entityId, $m)) {
        $uniqueLines[$chatId] = ['title' => $item['title'] ?? '', 'phone' => $m[1], 'entity' => $entityId];
    }
}

echo PHP_EOL . 'Unique 1:1 LINES leads: ' . count($uniqueLines) . PHP_EOL;
foreach ($uniqueLines as $id => $meta) {
    echo "  chat$id | {$meta['phone']} | {$meta['title']}" . PHP_EOL;
}
