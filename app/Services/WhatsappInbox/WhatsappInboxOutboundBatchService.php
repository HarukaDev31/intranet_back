<?php

namespace App\Services\WhatsappInbox;

use App\Jobs\WhatsappInbox\SendWaInboxOutboundJob;
use App\Models\WhatsappInbox\WaInboxMessage;
use App\Models\WhatsAppCoordinacionBatch;
use App\Models\WhatsAppCoordinacionBatchItem;
use App\Support\WhatsApp\WaInboxJobContext;
use App\Support\WhatsApp\WaInboxLog;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Batch 2 (Horizon): envío Meta de mensajes pending tras el batch programático (batch 1).
 */
class WhatsappInboxOutboundBatchService
{
    /**
     * @return string|null Laravel batch UUID
     */
    public function dispatchForCoordinacionBatch(int $coordinacionBatchId)
    {
        $batch = WhatsAppCoordinacionBatch::query()->find($coordinacionBatchId);
        if ($batch === null) {
            return null;
        }

        $domain = $batch->job_domain !== null && $batch->job_domain !== ''
            ? (string) $batch->job_domain
            : null;

        $items = WhatsAppCoordinacionBatchItem::query()
            ->where('batch_id', $coordinacionBatchId)
            ->where('status', WhatsAppCoordinacionBatchItem::STATUS_COMPLETED)
            ->whereNotNull('inbox_message_id')
            ->orderBy('sort_order')
            ->get();

        $stepDelay = max(0, min((int) config('meta_whatsapp.inbox_outbound_step_delay_seconds', 2), 30));
        $queue = (string) config('meta_whatsapp.inbox_queue', 'notificaciones');

        $jobs = [];
        $jobIndex = 0;
        foreach ($items as $item) {
            $message = WaInboxMessage::query()->find((int) $item->inbox_message_id);
            if ($message === null || $message->direction !== 'out') {
                continue;
            }
            if ($message->delivery_status !== 'pending') {
                continue;
            }

            $jobs[] = new SendWaInboxOutboundJob(
                (int) $message->id,
                $domain,
                (string) $item->label,
                $jobIndex > 0 ? $stepDelay : 0
            );
            $jobIndex++;
        }

        if ($jobs === []) {
            WaInboxLog::info('outboundBatch.skip_empty', [
                'coordinacion_batch_id' => $coordinacionBatchId,
            ]);

            return null;
        }

        $tipoLabels = [
            'rotulado' => 'Rotulado',
            'solicitar_documentos' => 'Docs consolidado',
            'calculadora' => 'Calculadora importación',
            'entrega_form' => 'Formulario entrega',
            'docs_recordatorio' => 'Recordatorio documentos',
        ];
        $tipoLabel = isset($tipoLabels[$batch->tipo])
            ? $tipoLabels[$batch->tipo]
            : ucfirst(str_replace('_', ' ', (string) $batch->tipo));

        $horizonName = sprintf(
            'Inbox envío · %s · carga %s · cot. #%s%s',
            $tipoLabel,
            $batch->carga ?? '-',
            $batch->id_cotizacion ?? '-',
            $batch->cliente ? ' · ' . mb_substr((string) $batch->cliente, 0, 35) : ''
        );

        if (count($jobs) === 1) {
            dispatch($jobs[0])->onQueue($queue);
            $outboundRef = null;
        } else {
            Bus::chain($jobs)->onQueue($queue)->dispatch();
            $outboundRef = sprintf('chain-%d', $coordinacionBatchId);
        }

        $batch->update([
            'outbound_laravel_batch_id' => $outboundRef,
        ]);

        Log::info('WhatsappInboxOutboundBatch: jobs despachados (cadena secuencial)', [
            'coordinacion_batch_id' => $coordinacionBatchId,
            'outbound_ref' => $outboundRef,
            'horizon_label' => $horizonName,
            'total_jobs' => count($jobs),
            'step_delay_seconds' => $stepDelay,
            'domain' => WaInboxJobContext::resolveJobDomain($domain),
        ]);

        return $outboundRef;
    }
}
