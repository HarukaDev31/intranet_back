<?php

namespace App\Jobs\WhatsApp;

use App\Models\WhatsAppCoordinacionBatchItem;
use App\Services\WhatsApp\WhatsAppCoordinacionBatchService;
use App\Services\WhatsappInbox\WhatsappInboxCoordinacionOutboundService;
use App\Traits\DatabaseConnectionTrait;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCoordinacionWhatsAppJob implements ShouldQueue
{
    use Batchable;
    use DatabaseConnectionTrait;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var array<string, mixed> */
    public $payload;

    /** @var int|null */
    public $batchItemId;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(array $payload, ?int $batchItemId = null)
    {
        $this->payload = $payload;
        $this->batchItemId = $batchItemId ?? (isset($payload['batch_item_id']) ? (int) $payload['batch_item_id'] : null);
        $this->onQueue((string) config('meta_whatsapp.queue', 'notificaciones'));
    }

    /**
     * Nombre visible en Horizon (lista del batch).
     */
    public function displayName(): string
    {
        if (!empty($this->payload['_batch_label'])) {
            return (string) $this->payload['_batch_label'];
        }

        if ($this->batchItemId !== null) {
            try {
                $item = WhatsAppCoordinacionBatchItem::query()->find($this->batchItemId);
                if ($item !== null && $item->label !== '') {
                    return $item->label;
                }
            } catch (\Throwable $e) {
                // Horizon solo necesita un nombre; no fallar si MySQL no está accesible desde WSL.
            }
        }

        $template = $this->payload['template'] ?? $this->payload['type'] ?? 'mensaje';

        return 'WhatsApp: ' . $template;
    }

    public function handle(
        WhatsappInboxCoordinacionOutboundService $inboxService,
        WhatsAppCoordinacionBatchService $batchService
    ): void {
        $domain = isset($this->payload['_domain']) ? (string) $this->payload['_domain'] : null;
        if ($domain !== null && $domain !== '') {
            $this->setDatabaseConnection($domain);
        } else {
            $this->setDatabaseConnection('localhost');
        }

        if ($this->batchItemId !== null) {
            $batchService->markItemProcessing($this->batchItemId);
        }

        $sleep = (int) ($this->payload['sleep'] ?? 0);
        if ($sleep > 0) {
            sleep(min($sleep, 120));
        }

        try {
            $result = $inboxService->process($this->payload);

            if (empty($result['status'])) {
                $error = (string) ($result['error'] ?? 'Error desconocido en envío Meta');
                if ($this->batchItemId !== null) {
                    $batchService->markItemFailed($this->batchItemId, $error);
                }
                Log::error('SendCoordinacionWhatsAppJob: fallo envío', [
                    'batch_item_id' => $this->batchItemId,
                    'payload_type' => $this->payload['type'] ?? 'template',
                    'template' => $this->payload['template'] ?? null,
                    'error' => $result['error'] ?? $result['response'] ?? null,
                ]);

                return;
            }

            if ($this->batchItemId !== null) {
                $inboxMessageId = isset($result['inbox_message_id']) ? (int) $result['inbox_message_id'] : null;
                $batchService->markItemCompleted($this->batchItemId, $inboxMessageId);
            }
        } catch (\Throwable $e) {
            if ($this->batchItemId !== null) {
                $batchService->markItemFailed($this->batchItemId, $e->getMessage());
            }

            throw $e;
        }
    }
}
