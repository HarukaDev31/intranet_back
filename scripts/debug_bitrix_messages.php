<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$base = 'https://probusiness.bitrix24.es/rest/23/dmhw4owllz0zt2rs/';
$chats = [
    ['chat105935', 'Patricia Alban WhatsApp 5', '33'],
    ['chat55101', 'Ventas WhatsApp 4', '25'],
];

foreach ($chats as [$dialog, $label, $connectorLineId]) {
    echo PHP_EOL . "=== $label ($dialog) connector=$connectorLineId ===" . PHP_EOL;
    $r = Illuminate\Support\Facades\Http::timeout(30)->get($base . 'im.dialog.messages.get.json', [
        'DIALOG_ID' => $dialog,
        'LIMIT' => 5,
    ]);
    $messages = $r->json('result.messages') ?? [];
    foreach ($messages as $msg) {
        echo 'msg id=' . ($msg['id'] ?? '') . ' text=' . substr((string) ($msg['text'] ?? ''), 0, 40) . PHP_EOL;
        echo '  params=' . json_encode($msg['params'] ?? [], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
