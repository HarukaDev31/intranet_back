<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$base181 = rtrim(env('BITRIX_WEBHOOK_URL', ''), '/') . '/';
$base23 = 'https://probusiness.bitrix24.es/rest/23/' . trim(env('BITRIX_WEBHOOK_TOKEN_IM', ''), '/') . '/';

$contactId = 5263;
foreach ([
    ['CRM_ENTITY_TYPE' => 'contact', 'CRM_ENTITY' => $contactId],
    ['CRM_ENTITY_TYPE' => 'CONTACT', 'CRM_ENTITY' => $contactId, 'CRM_ENTITY_ID' => $contactId],
    ['ENTITY_TYPE' => 'contact', 'ENTITY_ID' => $contactId],
] as $params) {
    $r = Illuminate\Support\Facades\Http::timeout(30)->post($base181 . 'imopenlines.crm.chat.get.json', $params);
    echo 'crm.chat.get ' . json_encode($params) . ' => ' . $r->status() . ' ' . json_encode($r->json()) . PHP_EOL;
}

// Try dialog get by entity
$r = Illuminate\Support\Facades\Http::timeout(30)->post($base181 . 'imopenlines.dialog.get.json', [
    'ENTITY_TYPE' => 'LINES',
    'ENTITY_ID' => 'bitrix_whatcrm_net_70680444|25|chat.51974631688|11727',
]);
echo 'dialog.get entity => ' . json_encode($r->json(), JSON_UNESCAPED_UNICODE) . PHP_EOL;

// im.dialog.messages with 181 vs 23
foreach (['181' => $base181, '23' => $base23] as $label => $base) {
    $r = Illuminate\Support\Facades\Http::timeout(30)->get($base . 'im.dialog.messages.get.json', [
        'DIALOG_ID' => 'chat114651',
        'LIMIT' => 5,
    ]);
    echo "messages.get $label => HTTP {$r->status()} count=" . count($r->json('result.messages') ?? []) . PHP_EOL;
}

// CRM deal list with open lines source?
$r = Illuminate\Support\Facades\Http::timeout(30)->get($base181 . 'crm.deal.list.json', [
    'select' => ['ID', 'TITLE', 'CONTACT_ID', 'DATE_MODIFY'],
    'filter' => ['>DATE_MODIFY' => '2026-01-01'],
    'order' => ['DATE_MODIFY' => 'DESC'],
    'start' => 0,
]);
echo 'deals recent: ' . count($r->json('result') ?? []) . PHP_EOL;
