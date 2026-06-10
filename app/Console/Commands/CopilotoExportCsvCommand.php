<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CopilotoExportCsvCommand extends Command
{
    protected $signature = 'copiloto:export-csv
        {--limit-deals=100 : Máximo de deals WON a procesar}
        {--output= : Ruta del CSV (default: storage/app/conversaciones_WON.csv)}';

    protected $description = 'Exporta conversaciones de chats WON de Bitrix a CSV usando imopenlines.session.history.get.';

    private string $baseUrl = 'https://probusiness.bitrix24.es/rest/23/xva3ovmip64j1171/';

    public function handle(): int
    {
        $limitDeals = max(1, (int) $this->option('limit-deals'));
        $outputPath = $this->option('output') ?: storage_path('app/conversaciones_WON.csv');

        $this->info("Iniciando exportación — max deals: {$limitDeals}");

        // ── 1. Deals WON ──────────────────────────────────────────────────
        $this->info('Obteniendo deals WON...');
        $deals = $this->fetchWonDeals($limitDeals);
        $this->line('Deals WON encontrados: ' . count($deals));

        if (empty($deals)) {
            $this->warn('No se encontraron deals WON.');
            return 0;
        }

        // ── 2. Abrir CSV ──────────────────────────────────────────────────
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($outputPath, 'w');
        if (!$fp) {
            $this->error("No se pudo abrir: {$outputPath}");
            return 1;
        }

        fwrite($fp, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel

        fputcsv($fp, [
            'deal_id', 'deal_title', 'monto',
            'fecha_creacion_deal', 'fecha_cierre_deal',
            'session_id', 'chat_id', 'chat_subject',
            'msg_id', 'fecha_mensaje', 'direccion',
            'autor_tipo', 'autor_nombre', 'mensaje',
        ]);

        $totalMsgs      = 0;
        $processedChats = 0;
        $sinChats       = 0;
        $errores        = 0;

        // ── 3. Por cada deal, buscar sessions y mensajes ──────────────────
        foreach ($deals as $deal) {
            $dealId = (string) $deal['ID'];
            $this->line("Deal {$dealId} — {$deal['TITLE']}");

            $sessions = $this->fetchDealSessions($dealId);

            if (empty($sessions)) {
                $this->line('  → Sin sesiones de chat');
                $sinChats++;
                usleep(200000);
                continue;
            }

            foreach ($sessions as $session) {
                $sessionId  = (string) $session['ASSOCIATED_ENTITY_ID'];
                $chatSubject = (string) ($session['SUBJECT'] ?? '');

                if (empty($sessionId) || $sessionId === '0') {
                    continue;
                }

                $this->line("  Session {$sessionId} — {$chatSubject}");

                $result = $this->fetchSessionHistory($sessionId);

                if ($result['error']) {
                    $this->warn("  → Error: " . $result['error_msg']);
                    $errores++;
                    usleep(300000);
                    continue;
                }

                $messages = $result['messages'];
                $users    = $result['users'];
                $chatId   = $result['chat_id'];

                // Mapa de usuarios
                $userMap = [];
                foreach ($users as $uid => $u) {
                    $userMap[(string) $uid] = [
                        'nombre' => $u['name'] ?? 'Desconocido',
                        'tipo'   => ($u['bot'] ?? false) ? 'BOT'
                                  : (($u['connector'] ?? false) ? 'CLIENTE' : 'VENDEDOR'),
                    ];
                }

                foreach ($messages as $msgId => $msg) {
                    $senderId = (string) ($msg['senderid'] ?? '0');
                    if ($senderId === '0') continue;

                    $text = $this->cleanText((string) ($msg['text'] ?? ''));
                    if ($text === '') continue;

                    // Detectar dirección por connectorMid
                    $connectorMid = '';
                    $params = $msg['params'] ?? [];
                    if (!empty($params['connectorMid'][0])) {
                        $connectorMid = (string) $params['connectorMid'][0];
                    }

                    if (str_starts_with($connectorMid, 'true_')) {
                        $tipo = 'VENDEDOR';
                    } elseif (str_starts_with($connectorMid, 'false_')) {
                        $tipo = 'CLIENTE';
                    } else {
                        $tipo = $userMap[$senderId]['tipo'] ?? 'INTERNO';
                    }

                    $nombre = $userMap[$senderId]['nombre'] ?? 'Desconocido';
                    $fecha  = substr((string) ($msg['date'] ?? ''), 0, 16);
                    $dir    = ($tipo === 'VENDEDOR') ? 'out' : 'in';

                    fputcsv($fp, [
                        $dealId,
                        $deal['TITLE'] ?? '',
                        $deal['OPPORTUNITY'] ?? '0',
                        substr((string) ($deal['DATE_CREATE'] ?? ''), 0, 16),
                        substr((string) ($deal['DATE_MODIFY'] ?? ''), 0, 16),
                        $sessionId,
                        $chatId,
                        $chatSubject,
                        $msgId,
                        $fecha,
                        $dir,
                        $tipo,
                        $nombre,
                        $text,
                    ]);

                    $totalMsgs++;
                }

                $processedChats++;
                usleep(400000);
            }

            usleep(200000);
        }

        fclose($fp);

        $this->info("✅ CSV generado: {$outputPath}");
        $this->info("   Deals procesados  : " . count($deals));
        $this->info("   Chats procesados  : {$processedChats}");
        $this->info("   Mensajes escritos : {$totalMsgs}");
        $this->info("   Sin chats         : {$sinChats}");
        $this->info("   Errores           : {$errores}");

        return 0;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function fetchWonDeals(int $limit): array
    {
        $all   = [];
        $start = 0;

        do {
            $resp = Http::timeout(30)->get($this->baseUrl . 'crm.deal.list.json', [
                'filter[STAGE_ID]'   => 'WON',
                'order[DATE_MODIFY]' => 'DESC',
                'select[0]'          => 'ID',
                'select[1]'          => 'TITLE',
                'select[2]'          => 'OPPORTUNITY',
                'select[3]'          => 'DATE_CREATE',
                'select[4]'          => 'DATE_MODIFY',
                'start'              => $start,
            ]);

            $result = data_get($resp->json(), 'result', []);
            $all    = array_merge($all, $result);
            $next   = data_get($resp->json(), 'next');
            $start  = $next ?? null;

            usleep(200000);
        } while ($start !== null && count($all) < $limit);

        return array_slice($all, 0, $limit);
    }

    private function fetchDealSessions(string $dealId): array
    {
        // GET con URL pre-encoded — única forma que funciona con esta API
        $url = $this->baseUrl . 'crm.activity.list.json'
            . '?filter%5BOWNER_TYPE_ID%5D=2'
            . '&filter%5BOWNER_ID%5D=' . $dealId
            . '&filter%5BPROVIDER_ID%5D=IMOPENLINES_SESSION'
            . '&select%5B0%5D=ID'
            . '&select%5B1%5D=SUBJECT'
            . '&select%5B2%5D=ASSOCIATED_ENTITY_ID';

        $resp       = Http::timeout(30)->get($url);
        $activities = data_get($resp->json(), 'result', []);

        // Filtrar solo chats reales y que tengan ASSOCIATED_ENTITY_ID
        return array_filter($activities, function ($act) {
            $subject = (string) ($act['SUBJECT'] ?? '');
            $assocId = (string) ($act['ASSOCIATED_ENTITY_ID'] ?? '0');
            return str_contains($subject, 'Chat de Canal Abierto') && $assocId !== '0';
        });
    }

    private function fetchSessionHistory(string $sessionId): array
    {
        $resp = Http::timeout(30)->post($this->baseUrl . 'imopenlines.session.history.get.json', [
            'SESSION_ID' => $sessionId,
        ]);

        $body = $resp->json();

        if (data_get($body, 'error')) {
            return [
                'error'     => true,
                'error_msg' => data_get($body, 'error_description', 'Unknown error'),
                'messages'  => [],
                'users'     => [],
                'chat_id'   => '',
            ];
        }

        return [
            'error'    => false,
            'messages' => data_get($body, 'result.message', []),
            'users'    => data_get($body, 'result.users', []),
            'chat_id'  => (string) data_get($body, 'result.chatId', ''),
        ];
    }

    private function cleanText(string $text): string
    {
        $clean = preg_replace('/\[.*?\]/s', '', $text);
        return trim((string) $clean);
    }
}
