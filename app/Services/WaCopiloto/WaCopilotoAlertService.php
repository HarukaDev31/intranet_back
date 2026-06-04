<?php

namespace App\Services\WaCopiloto;

use App\Services\WhatsApp\MetaWhatsAppCoordinacionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WaCopilotoAlertService
{
    /**
     * @return int Cantidad de alertas enviadas
     */
    public function notifyIfFailedJobs()
    {
        $alertPhone = preg_replace('/\D+/', '', (string) config('meta_whatsapp_copiloto.inbox_alert_phone'));
        if ($alertPhone === '') {
            return 0;
        }

        if (!Schema::hasTable('failed_jobs')) {
            return 0;
        }

        $needles = [
            'ProcessWaCopilotoInboundJob',
            'SendWaCopilotoOutboundJob',
        ];

        $rows = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(20)
            ->get();

        $sent = 0;
        foreach ($rows as $row) {
            $payload = (string) $row->payload;
            $match = false;
            foreach ($needles as $needle) {
                if (strpos($payload, $needle) !== false) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                continue;
            }

            $text = '⚠️ WhatsApp Inbox: job fallido (' . $row->queue . '). Revisar Horizon/failed_jobs.';
            try {
                $meta = app(MetaWhatsAppCoordinacionService::class);
                $meta->sendMetaTemplate(
                    $alertPhone,
                    'pb_entrega_recordatorio_v1',
                    (string) config('meta_whatsapp_copiloto.default_language', 'es_PE'),
                    [['type' => 'text', 'text' => mb_substr($text, 0, 900)]],
                    null,
                    false,
                    [
                        'body_preview' => $text,
                        'source' => 'inbox_alert_failed_jobs',
                        'contact_name' => 'Alertas sistema',
                    ]
                );
                $sent++;
            } catch (\Exception $e) {
                Log::error('WaCopilotoAlert: no se pudo enviar alerta', [
                    'message' => $e->getMessage(),
                ]);
            }

            break;
        }

        return $sent;
    }
}
