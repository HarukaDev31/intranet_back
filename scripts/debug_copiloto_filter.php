<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cmd = new App\Console\Commands\CopilotoSyncHistoricoCommand(app(App\Services\Copiloto\CopilotoMessageService::class));
$ref = new ReflectionClass($cmd);

$parse = $ref->getMethod('parseLineIdsFromEnv');
$parse->setAccessible(true);
$ventas = $parse->invoke($cmd, 'BITRIX_LINEA_VENTAS_CC', '9240,25');
$consolidado = $parse->invoke($cmd, 'BITRIX_LINEA_CONSOLIDADO', '11784,33');
$filter = array_merge($ventas, $consolidado);
echo 'ventas=' . json_encode($ventas) . PHP_EOL;
echo 'consolidado=' . json_encode($consolidado) . PHP_EOL;

$fetch = $ref->getMethod('fetchAllRecentItems');
$fetch->setAccessible(true);
$baseUrl = 'https://probusiness.bitrix24.es/rest/23/dmhw4owllz0zt2rs/';
$items = $fetch->invoke($cmd, $baseUrl);

$isOpen = $ref->getMethod('isOpenLinesRecentItem');
$isOpen->setAccessible(true);
$extract = $ref->getMethod('extractEntityIdFromRecentItem');
$extract->setAccessible(true);
$matches = $ref->getMethod('matchesConfiguredLineIds');
$matches->setAccessible(true);
$filterMethod = $ref->getMethod('filterLineChats');
$filterMethod->setAccessible(true);

$linesCount = 0;
foreach ($items as $item) {
    if ($isOpen->invoke($cmd, $item)) {
        $linesCount++;
        $entityId = $extract->invoke($cmd, $item);
        echo 'LINES: ' . ($item['title'] ?? '') . ' | ' . $entityId . ' | match=' . ($matches->invoke($cmd, $entityId, $filter) ? 'yes' : 'no') . PHP_EOL;
    }
}
echo 'total items=' . count($items) . ' lines items=' . $linesCount . PHP_EOL;
$filtered = $filterMethod->invoke($cmd, $items, $filter, false);
echo 'filtered=' . count($filtered) . PHP_EOL;
