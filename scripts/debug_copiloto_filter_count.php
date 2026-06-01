<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cmd = app(App\Console\Commands\CopilotoSyncHistoricoCommand::class);
$ref = new ReflectionClass($cmd);
$baseUrl = 'https://probusiness.bitrix24.es/rest/23/dmhw4owllz0zt2rs/';

$fetch = $ref->getMethod('fetchAllRecentItems');
$fetch->setAccessible(true);
$filter = $ref->getMethod('filterLineChats');
$filter->setAccessible(true);
$isOpen = $ref->getMethod('isOpenLinesRecentItem');
$isOpen->setAccessible(true);
$extractEntity = $ref->getMethod('extractEntityIdFromRecentItem');
$extractEntity->setAccessible(true);
$matches = $ref->getMethod('matchesConfiguredLineIds');
$matches->setAccessible(true);
$isGroup = $ref->getMethod('isInternalWhatsappGroupChat');
$isGroup->setAccessible(true);
$skip = $ref->getMethod('shouldSkipInternalChat');
$skip->setAccessible(true);
$phoneFromChat = $ref->getMethod('extractPhoneFromRecentChat');
$phoneFromChat->setAccessible(true);
$parse = $ref->getMethod('parseLineIdsFromEnv');
$parse->setAccessible(true);

$ventas = $parse->invoke($cmd, 'BITRIX_LINEA_VENTAS_CC', '9240,25');
$consolidado = $parse->invoke($cmd, 'BITRIX_LINEA_CONSOLIDADO', '11784,33');
$filterIds = array_merge($ventas, $consolidado);

$items = $fetch->invoke($cmd, $baseUrl);
$lines = 0;
$linesMatch = 0;
$byConnector = [];
$filtered = $filter->invoke($cmd, $items, $filterIds, false);

echo 'Total im.recent.list: ' . count($items) . PHP_EOL;
echo 'Filter line IDs: ' . implode(',', $filterIds) . PHP_EOL;

foreach ($items as $item) {
    if (!$isOpen->invoke($cmd, $item)) {
        continue;
    }
    $lines++;
    $entityId = $extractEntity->invoke($cmd, $item);
    if (preg_match('/bitrix_whatcrm_net_\d+\|(\d+)\|/', $entityId, $m)) {
        $byConnector[$m[1]] = ($byConnector[$m[1]] ?? 0) + 1;
    }
    if ($matches->invoke($cmd, $entityId, $filterIds)) {
        $linesMatch++;
    }
}

echo 'Open Lines (LINES): ' . $lines . PHP_EOL;
echo 'Open Lines matching ventas/consolidado IDs: ' . $linesMatch . PHP_EOL;
echo 'After filterLineChats (incl. duplicates): ' . count($filtered) . PHP_EOL;
echo 'By connector ID in entity_id:' . PHP_EOL;
ksort($byConnector);
foreach ($byConnector as $id => $count) {
    echo "  connector $id => $count chats" . PHP_EOL;
}

$unique = [];
$wouldSync = 0;
foreach ($filtered as $chat) {
    $id = $chat['chat_id'] ?? $chat['id'] ?? '';
    if (isset($unique[$id])) {
        continue;
    }
    $unique[$id] = true;
    $phone = $phoneFromChat->invoke($cmd, $chat);
    $skipped = $skip->invoke($cmd, $chat, $phone);
    $group = $isGroup->invoke($cmd, $chat);
    echo PHP_EOL . '--- ' . ($chat['title'] ?? '') . ' ---' . PHP_EOL;
    echo '  entity: ' . $extractEntity->invoke($cmd, $chat) . PHP_EOL;
    echo '  phone: ' . ($phone ?: '(none)') . ' | group=' . ($group ? 'yes' : 'no') . ' | skip=' . ($skipped ? 'yes' : 'no') . PHP_EOL;
    if (!$skipped) {
        $wouldSync++;
    }
}

echo PHP_EOL . 'Unique filtered chats: ' . count($unique) . PHP_EOL;
echo 'Would actually sync (1:1 leads): ' . $wouldSync . PHP_EOL;
