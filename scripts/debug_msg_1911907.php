<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$baseUrl = 'https://probusiness.bitrix24.es/rest/23/dmhw4owllz0zt2rs/';
$cmd = app(App\Console\Commands\CopilotoSyncHistoricoCommand::class);
$ref = new ReflectionClass($cmd);
$bitrixGet = $ref->getMethod('bitrixGet');
$bitrixGet->setAccessible(true);
$normalize = $ref->getMethod('normalizeBitrixMessage');
$normalize->setAccessible(true);
$svc = app(App\Services\Copiloto\CopilotoMessageService::class);

$ventas = array_map('trim', explode(',', env('BITRIX_LINEA_VENTAS_CC', '9240,25')));
$consolidado = array_map('trim', explode(',', env('BITRIX_LINEA_CONSOLIDADO', '11784,33')));

$lastId = null;
for ($page = 0; $page < 10; $page++) {
    $query = ['DIALOG_ID' => 'chat24735', 'LIMIT' => 50];
    if ($lastId) {
        $query['LAST_ID'] = $lastId;
    }
    $payload = $bitrixGet->invoke($cmd, $baseUrl, 'im.dialog.messages.get', $query);
    $messages = $payload['result']['messages'] ?? [];
    $users = $payload['result']['users'] ?? [];
    foreach ($messages as $message) {
        if ((string) ($message['id'] ?? '') === '1911907') {
            echo json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            $normalized = $normalize->invoke($cmd, $message, $users, $ventas, $consolidado, null, 'ventas_cc');
            echo 'normalized=' . json_encode($normalized, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            $svc->persistMessage($normalized);
            echo 'persist OK' . PHP_EOL;
            exit(0);
        }
    }
    $last = end($messages);
    $lastId = $last['id'] ?? null;
    if (!$lastId) {
        break;
    }
}
echo 'message not found' . PHP_EOL;
