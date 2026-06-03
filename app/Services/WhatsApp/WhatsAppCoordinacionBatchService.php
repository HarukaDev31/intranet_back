<?php

namespace App\Services\WhatsApp;

use App\Jobs\WhatsApp\SendCoordinacionWhatsAppJob;
use App\Models\WhatsAppCoordinacionBatch;
use App\Models\WhatsAppCoordinacionBatchItem;
use Illuminate\Bus\Batch;
use App\Jobs\WhatsApp\FinalizeWhatsAppCoordinacionBatchCallback;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class WhatsAppCoordinacionBatchService
{
    /** @var array<int, array<int, SendCoordinacionWhatsAppJob>> */
    private static array $bufferedJobs = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function create(string $tipo, array $context = []): WhatsAppCoordinacionBatch
    {
        return WhatsAppCoordinacionBatch::query()->create([
            'tipo' => $tipo,
            'id_cotizacion' => $context['id_cotizacion'] ?? null,
            'phone_e164' => $context['phone_e164'] ?? null,
            'cliente' => $context['cliente'] ?? null,
            'carga' => $context['carga'] ?? null,
            'status' => WhatsAppCoordinacionBatch::STATUS_PENDING,
        ]);
    }

    /**
     * Registra un paso y prepara el job (sin despachar hasta dispatchBuffered).
     *
     * @param  array<string, mixed>  $payload
     */
    public function enqueueItem(
        int $batchId,
        string $stepKey,
        string $label,
        array $payload
    ): SendCoordinacionWhatsAppJob {
        $batch = WhatsAppCoordinacionBatch::query()->findOrFail($batchId);
        $sortOrder = (int) $batch->items()->count() + 1;

        $item = WhatsAppCoordinacionBatchItem::query()->create([
            'batch_id' => $batchId,
            'sort_order' => $sortOrder,
            'step_key' => $stepKey,
            'label' => mb_substr($label, 0, 255),
            'template_name' => isset($payload['template']) ? (string) $payload['template'] : null,
            'payload_type' => (string) ($payload['type'] ?? 'template'),
            'status' => WhatsAppCoordinacionBatchItem::STATUS_PENDING,
        ]);

        $payload['batch_item_id'] = $item->id;
        $payload['_batch_label'] = $label;
        $job = new SendCoordinacionWhatsAppJob($payload, $item->id);
        self::$bufferedJobs[$batchId][] = $job;

        $batch->increment('total_items');

        return $job;
    }

    public function dispatchBuffered(int $batchId): ?string
    {
        $jobs = self::$bufferedJobs[$batchId] ?? [];
        unset(self::$bufferedJobs[$batchId]);

        $batch = WhatsAppCoordinacionBatch::query()->find($batchId);
        if ($batch === null) {
            return null;
        }

        if ($jobs === []) {
            $batch->update([
                'status' => WhatsAppCoordinacionBatch::STATUS_COMPLETED,
                'finished_at' => now(),
            ]);

            return null;
        }

        $batchIdRef = $batchId;
        $tipoLabels = [
            'rotulado' => 'Rotulado',
            'solicitar_documentos' => 'Docs consolidado',
        ];
        $tipoLabel = $tipoLabels[$batch->tipo] ?? ucfirst(str_replace('_', ' ', (string) $batch->tipo));
        $horizonName = sprintf(
            '%s · carga %s · cot. #%s%s',
            $tipoLabel,
            $batch->carga ?? '-',
            $batch->id_cotizacion ?? '-',
            $batch->cliente ? ' · ' . mb_substr($batch->cliente, 0, 35) : ''
        );
        $laravelBatch = Bus::batch($jobs)
            ->name($horizonName)
            ->allowFailures()
            ->onQueue((string) config('meta_whatsapp.queue', 'notificaciones'))
            ->finally([new FinalizeWhatsAppCoordinacionBatchCallback($batchIdRef)])
            ->dispatch();

        $batch->update([
            'laravel_batch_id' => $laravelBatch->id,
            'status' => WhatsAppCoordinacionBatch::STATUS_RUNNING,
            'dispatched_at' => now(),
        ]);

        Log::info('WhatsAppCoordinacionBatch: jobs despachados', [
            'batch_id' => $batchId,
            'laravel_batch_id' => $laravelBatch->id,
            'total_jobs' => count($jobs),
        ]);

        return $laravelBatch->id;
    }

    public function markItemProcessing(int $itemId): void
    {
        try {
            WhatsAppCoordinacionBatchItem::query()
                ->whereKey($itemId)
                ->where('status', WhatsAppCoordinacionBatchItem::STATUS_PENDING)
                ->update([
                    'status' => WhatsAppCoordinacionBatchItem::STATUS_PROCESSING,
                    'started_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsAppCoordinacionBatch: no se pudo marcar ítem processing', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function markItemCompleted(int $itemId, ?int $bitrixRegistroId = null): void
    {
        try {
            $item = WhatsAppCoordinacionBatchItem::query()->find($itemId);
            if ($item === null) {
                return;
            }

            $payload = [
                'status' => WhatsAppCoordinacionBatchItem::STATUS_COMPLETED,
                'finished_at' => now(),
                'last_error' => null,
            ];
            if ($bitrixRegistroId !== null) {
                $payload['bitrix_registro_id'] = $bitrixRegistroId;
            }
            $item->update($payload);

            $this->reconcileBatch((int) $item->batch_id);
        } catch (\Throwable $e) {
            Log::warning('WhatsAppCoordinacionBatch: no se pudo marcar ítem completed', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function markItemFailed(int $itemId, string $error): void
    {
        try {
            $item = WhatsAppCoordinacionBatchItem::query()->find($itemId);
            if ($item === null) {
                return;
            }

            $item->update([
                'status' => WhatsAppCoordinacionBatchItem::STATUS_FAILED,
                'last_error' => mb_substr($error, 0, 500),
                'finished_at' => now(),
            ]);

            $this->reconcileBatch((int) $item->batch_id);
        } catch (\Throwable $e) {
            Log::warning('WhatsAppCoordinacionBatch: no se pudo marcar ítem failed', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function finalizeFromLaravelBatch(int $batchId, Batch $laravelBatch): void
    {
        try {
            $batch = WhatsAppCoordinacionBatch::query()->find($batchId);
            if ($batch === null) {
                return;
            }

            $this->reconcileBatch($batchId);

            $batch->refresh();
            $batch->update([
                'laravel_batch_id' => $laravelBatch->id,
                'finished_at' => $batch->finished_at ?? now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsAppCoordinacionBatch: finalize falló', [
                'batch_id' => $batchId,
                'laravel_batch_id' => $laravelBatch->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function reconcileBatch(int $batchId): void
    {
        $batch = WhatsAppCoordinacionBatch::query()->find($batchId);
        if ($batch === null) {
            return;
        }

        $completed = $batch->items()
            ->where('status', WhatsAppCoordinacionBatchItem::STATUS_COMPLETED)
            ->count();
        $failed = $batch->items()
            ->where('status', WhatsAppCoordinacionBatchItem::STATUS_FAILED)
            ->count();
        $total = (int) $batch->total_items;
        $done = $completed + $failed;

        $status = $batch->status;
        if ($total > 0 && $done >= $total) {
            if ($failed === 0) {
                $status = WhatsAppCoordinacionBatch::STATUS_COMPLETED;
            } elseif ($completed === 0) {
                $status = WhatsAppCoordinacionBatch::STATUS_FAILED;
            } else {
                $status = WhatsAppCoordinacionBatch::STATUS_PARTIAL;
            }
            $batch->finished_at = $batch->finished_at ?? now();
        } elseif ($batch->dispatched_at !== null && $done > 0) {
            $status = WhatsAppCoordinacionBatch::STATUS_RUNNING;
        }

        $batch->update([
            'completed_items' => $completed,
            'failed_items' => $failed,
            'status' => $status,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(int $batchId): array
    {
        $batch = WhatsAppCoordinacionBatch::query()
            ->with('items')
            ->findOrFail($batchId);

        return [
            'id' => $batch->id,
            'tipo' => $batch->tipo,
            'id_cotizacion' => $batch->id_cotizacion,
            'cliente' => $batch->cliente,
            'carga' => $batch->carga,
            'status' => $batch->status,
            'total_items' => $batch->total_items,
            'completed_items' => $batch->completed_items,
            'failed_items' => $batch->failed_items,
            'progress_percent' => $batch->progressPercent(),
            'laravel_batch_id' => $batch->laravel_batch_id,
            'dispatched_at' => $batch->dispatched_at !== null ? $batch->dispatched_at->toIso8601String() : null,
            'finished_at' => $batch->finished_at !== null ? $batch->finished_at->toIso8601String() : null,
            'items' => $batch->items->map(static function (WhatsAppCoordinacionBatchItem $item) {
                return [
                    'id' => $item->id,
                    'sort_order' => $item->sort_order,
                    'step_key' => $item->step_key,
                    'label' => $item->label,
                    'template_name' => $item->template_name,
                    'status' => $item->status,
                    'last_error' => $item->last_error,
                    'bitrix_registro_id' => $item->bitrix_registro_id,
                    'started_at' => $item->started_at !== null ? $item->started_at->toIso8601String() : null,
                    'finished_at' => $item->finished_at !== null ? $item->finished_at->toIso8601String() : null,
                ];
            })->values()->all(),
        ];
    }
}
