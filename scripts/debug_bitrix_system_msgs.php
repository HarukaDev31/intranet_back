<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$baseUrl = 'https://probusiness.bitrix24.es/rest/23/dmhw4owllz0zt2rs/';
$r = Illuminate\Support\Facades\Http::timeout(30)->get($baseUrl . 'im.dialog.messages.get.json', [
    'DIALOG_ID' => 'chat105935',
    'LIMIT' => 30,
]);
$messages = $r->json('result.messages') ?? [];
foreach ($messages as $msg) {
    $text = trim(strip_tags(preg_replace('/\[.*?\]/', '', (string) ($msg['text'] ?? ''))));
    $hasMid = !empty($msg['params']['CONNECTOR_MID'] ?? null);
    echo ($hasMid ? 'WA' : 'SYS') . ' | ' . substr($text, 0, 80) . PHP_EOL;
}
