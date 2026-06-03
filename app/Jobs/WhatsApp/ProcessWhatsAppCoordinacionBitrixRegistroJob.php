<?php

namespace App\Jobs\WhatsApp;

use App\Models\WhatsAppCoordinacionBitrixRegistro;
use App\Services\WhatsApp\MetaWhatsAppCoordinacionService;
use App\Traits\DatabaseConnectionTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppCoordinacionBitrixRegistroJob implements ShouldQueue
{
    use DatabaseConnectionTrait;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $tries = 3;

    /** @var array<int, int> */
    public $backoff = [5, 15, 30];

    /** @var int */
    public $registroId;

    /** @var string|null */
    public $domain;

    public function __construct(int $registroId, ?string $domain = null)
    {
        $this->registroId = $registroId;
        $this->domain = $domain;
        $this->onQueue((string) config('meta_whatsapp.bitrix_register_queue', config('meta_whatsapp.queue', 'notificaciones')));
    }

    public function handle(MetaWhatsAppCoordinacionService $service): void
    {
        if ($this->domain !== null && $this->domain !== '') {
            $this->setDatabaseConnection($this->domain);
        } else {
            $this->setDatabaseConnection('localhost');
        }

        $registro = WhatsAppCoordinacionBitrixRegistro::query()->find($this->registroId);
        if ($registro === null) {
            Log::warning('ProcessWhatsAppCoordinacionBitrixRegistroJob: registro no encontrado', [
                'registro_id' => $this->registroId,
            ]);

            return;
        }

        $payload = is_array($registro->payload_extra) ? $registro->payload_extra : [];
        if ($this->domain === null && !empty($payload['_domain'])) {
            $this->setDatabaseConnection((string) $payload['_domain']);
        }

        if (!$registro->isProcessable()) {
            Log::info('ProcessWhatsAppCoordinacionBitrixRegistroJob: registro omitido', [
                'registro_id' => $registro->id,
                'status' => $registro->status,
                'attempts' => $registro->attempts,
            ]);

            return;
        }

        try {
            $service->processQueuedBitrixRegistration($registro);
            $registro->markCompleted();
            Log::info('ProcessWhatsAppCoordinacionBitrixRegistroJob: registro Bitrix completado', [
                'registro_id' => $registro->id,
                'template' => $registro->template_name,
            ]);
        } catch (\Throwable $e) {
            $permanentlyFailed = $registro->recordAttemptFailure($e->getMessage());

            Log::warning('ProcessWhatsAppCoordinacionBitrixRegistroJob: error al registrar en Bitrix', [
                'registro_id' => $registro->id,
                'attempt' => $registro->attempts,
                'permanently_failed' => $permanentlyFailed,
                'error' => $e->getMessage(),
            ]);

            if ($permanentlyFailed) {
                return;
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $registro = WhatsAppCoordinacionBitrixRegistro::query()->find($this->registroId);
        if ($registro !== null && $registro->isProcessable()) {
            $registro->recordAttemptFailure($exception->getMessage());
        }

        Log::critical('ProcessWhatsAppCoordinacionBitrixRegistroJob: falló tras reintentos de cola', [
            'registro_id' => $this->registroId,
            'error' => $exception->getMessage(),
        ]);
    }
}
