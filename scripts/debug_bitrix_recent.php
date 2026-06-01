<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$url = 'https://probusiness.bitrix24.es/rest/23/dmhw4owllz0zt2rs/im.recent.list.json';
$response = Illuminate\Support\Facades\Http::timeout(30)->get($url);
$json = $response->json();
echo 'hasMore=' . json_encode($json['result']['hasMore'] ?? null) . ' next=' . json_encode($json['result']['next'] ?? null) . PHP_EOL;
$items = $json['result']['items'] ?? $json['result'] ?? [];

echo 'HTTP ' . $response->status() . PHP_EOL;
echo 'Items: ' . count($items) . PHP_EOL;

$types = [];
foreach ($items as $item) {
    $type = (string) ($item['type'] ?? '(empty)');
    $types[$type] = ($types[$type] ?? 0) + 1;
}
echo 'Types: ' . json_encode($types, JSON_UNESCAPED_UNICODE) . PHP_EOL;

foreach (array_slice($items, 0, 8) as $i => $item) {
    echo PHP_EOL . '=== item ' . $i . ' ===' . PHP_EOL;
    echo 'id=' . ($item['id'] ?? '') . PHP_EOL;
    echo 'type=' . ($item['type'] ?? '') . PHP_EOL;
    echo 'entity_id=' . ($item['entity_id'] ?? '') . PHP_EOL;
    echo 'title=' . ($item['title'] ?? '') . PHP_EOL;
    echo 'chat.entity_type=' . ($item['chat']['entity_type'] ?? '') . PHP_EOL;
    echo 'chat.entity_id=' . ($item['chat']['entity_id'] ?? '') . PHP_EOL;
    echo 'chat.extranet=' . json_encode($item['chat']['extranet'] ?? null) . PHP_EOL;
    echo 'options=' . json_encode($item['options'] ?? null, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

echo PHP_EOL . '=== All LINES chats ===' . PHP_EOL;
foreach ($items as $i => $item) {
    if (($item['chat']['entity_type'] ?? '') !== 'LINES') {
        continue;
    }
    echo "item $i | id=" . ($item['id'] ?? '') . ' | title=' . ($item['title'] ?? '') . PHP_EOL;
    echo '  entity_id=' . ($item['chat']['entity_id'] ?? '') . PHP_EOL;
}

$methods = ['imopenlines.config.list', 'imopenlines.network.list'];
foreach ($methods as $method) {
    echo PHP_EOL . '=== ' . $method . ' ===' . PHP_EOL;
    $resp = Illuminate\Support\Facades\Http::timeout(30)->get(
        'https://probusiness.bitrix24.es/rest/23/dmhw4owllz0zt2rs/' . $method . '.json'
    );
    echo 'HTTP ' . $resp->status() . PHP_EOL;
    $body = $resp->json();
    echo substr(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 0, 4000) . PHP_EOL;
}
