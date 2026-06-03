<?php

namespace App\Services\WhatsappInbox;

use App\Services\WhatsApp\MetaWhatsAppCoordinacionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WhatsappInboxAlertService
{
    /**
     * @return int Cantidad de alertas enviadas
     */
    public function notifyIfFailedJobs()
    {
        $alertPhone = preg_replace('/\D+/', '', (string) config('meta_whatsapp.inbox_alert_phone'));
        if ($alertPhone === '') {
            return 0;
        }

        if (!Schema::hasTable('failed_jobs')) {
            return 0;
        }

        $needles = [
            'ProcessWaInboxInboundJob',
            'SendWaInboxOutboundJob',
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
                    (string) config('meta_whatsapp.default_language', 'es_PE'),
                    [['type' => 'text', 'text' => mb_substr($text, 0, 900)]],
                    null
                );
                $sent++;
            } catch (\Exception $e) {
                Log::error('WhatsappInboxAlert: no se pudo enviar alerta', [
                    'message' => $e->getMessage(),
                ]);
            }

            break;
        }

        return $sent;
    }
}
