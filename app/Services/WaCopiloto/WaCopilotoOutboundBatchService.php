<?php

namespace App\Services\WaCopiloto;

use App\Jobs\WaCopiloto\SendWaCopilotoOutboundJob;
use App\Models\WaCopiloto\WaCopilotoMessage;
use App\Models\WhatsAppCoordinacionBatch;
use App\Models\WhatsAppCoordinacionBatchItem;
use App\Support\WhatsApp\WaCopilotoJobContext;
use App\Support\WhatsApp\WaCopilotoLog;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Batch 2 (Horizon): envío Meta de mensajes pending tras el batch programático (batch 1).
 */
class WaCopilotoOutboundBatchService
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

        $stepDelay = max(0, min((int) config('meta_whatsapp_copiloto.outbound_step_delay_seconds', 2), 30));
        $queue = (string) config('meta_whatsapp_copiloto.queue', 'notificaciones');

        $jobs = [];
        $jobIndex = 0;
        foreach ($items as $item) {
            $message = WaCopilotoMessage::query()->find((int) $item->inbox_message_id);
            if ($message === null || $message->direction !== 'out') {
                continue;
            }
            if ($message->delivery_status !== 'pending') {
                continue;
            }

            $jobs[] = new SendWaCopilotoOutboundJob(
                (int) $message->id,
                $domain,
                (string) $item->label,
                $jobIndex > 0 ? $stepDelay : 0
            );
            $jobIndex++;
        }

        if ($jobs === []) {
            WaCopilotoLog::info('outboundBatch.skip_empty', [
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

        Log::info('WaCopilotoOutboundBatch: jobs despachados (cadena secuencial)', [
            'coordinacion_batch_id' => $coordinacionBatchId,
            'outbound_ref' => $outboundRef,
            'horizon_label' => $horizonName,
            'total_jobs' => count($jobs),
            'step_delay_seconds' => $stepDelay,
            'domain' => WaCopilotoJobContext::resolveJobDomain($domain),
        ]);

        return $outboundRef;
    }
}
