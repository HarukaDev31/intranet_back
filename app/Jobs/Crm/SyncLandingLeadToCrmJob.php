<?php

namespace App\Jobs\Crm;

use App\Models\LandingConsolidadoLead;
use App\Models\LandingCursoLead;
use App\Services\Crm\LandingLeadCrmSyncResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncLandingLeadToCrmJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Máximo de fallos antes de marcar el lead como fallido permanente (alineado con modelos landing). */
    private const MAX_SYNC_FAILURES = LandingCursoLead::MAX_BITRIX_SYNC_FAILURES;

    /** @var int */
    public $tries = 3;

    /** @var array<int, int> */
    public $backoff = [10, 30, 60];

    /** @var string */
    public $funnel;

    /** @var int */
    public $leadId;

    /** @var int Segundos: evita encolar el mismo lead muchas veces mientras el cron corre cada minuto */
    public $uniqueFor = 300;

    public function __construct(string $funnel, int $leadId)
    {
        $this->funnel = $funnel;
        $this->leadId = $leadId;
        $this->onQueue(config('services.bitrix.queue', 'default'));
    }

    public function uniqueId(): string
    {
        return 'landing-bitrix:' . $this->funnel . ':' . $this->leadId;
    }

    public function handle(LandingLeadCrmSyncResolver $resolver): void
    {
        Log::info('[CRM] Iniciando sincronización de lead.', [
            'funnel' => $this->funnel,
            'lead_id' => $this->leadId,
            'attempt' => $this->attempts(),
            'queue' => config('services.bitrix.queue', 'default'),
        ]);

        $adapter = $resolver->resolve($this->funnel);
        if ($adapter === null) {
            Log::debug('[CRM] Sincronización omitida (webhook Bitrix no configurado).', [
                'funnel' => $this->funnel,
                'lead_id' => $this->leadId,
            ]);

            return;
        }

        $lead = $this->findLeadModel();
        if ($lead === null) {
            Log::warning('[CRM] Lead no encontrado para sincronizar.', [
                'funnel' => $this->funnel,
                'lead_id' => $this->leadId,
            ]);

            return;
        }

        if ($lead->bitrix_sync_failed_at !== null) {
            Log::info('[CRM] Lead ya marcado como fallido; se omite sincronización.', [
                'funnel' => $this->funnel,
                'lead_id' => $this->leadId,
                'bitrix_sync_errors' => (int) $lead->bitrix_sync_errors,
                'bitrix_sync_last_error' => $lead->bitrix_sync_last_error,
            ]);

            return;
        }

        try {
            $result = $adapter->sync($lead->toArray());
        } catch (\Throwable $e) {
            $permanentlyFailed = $this->recordSyncFailure($e);

            Log::warning('[CRM] Error al sincronizar lead.', [
                'funnel' => $this->funnel,
                'lead_id' => $this->leadId,
                'attempt' => $this->attempts(),
                'bitrix_sync_errors' => $this->currentSyncErrorCount(),
                'permanently_failed' => $permanentlyFailed,
                'error' => $e->getMessage(),
            ]);

            if ($permanentlyFailed) {
                return;
            }

            throw $e;
        }

        if (($result['skipped'] ?? false) === true) {
            Log::debug('[CRM] Sincronización omitida por adaptador.', [
                'funnel' => $this->funnel,
                'lead_id' => $this->leadId,
                'result' => $result,
            ]);
            $this->markBitrixSynced();

            return;
        }

        Log::info('[CRM] Lead sincronizado.', [
            'funnel' => $this->funnel,
            'lead_id' => $this->leadId,
            'result' => $result,
        ]);
        $this->markBitrixSynced();
    }

    /**
     * @return LandingConsolidadoLead|LandingCursoLead|null
     */
    private function findLeadModel()
    {
        if ($this->funnel === 'consolidado') {
            return LandingConsolidadoLead::query()->find($this->leadId);
        }
        if ($this->funnel === 'curso') {
            return LandingCursoLead::query()->find($this->leadId);
        }

        return null;
    }

    private function markBitrixSynced(): void
    {
        $updated = 0;
        if ($this->funnel === 'consolidado') {
            $updated = LandingConsolidadoLead::query()->whereKey($this->leadId)->update(['bitrix_synced_at' => now()]);
        } elseif ($this->funnel === 'curso') {
            $updated = LandingCursoLead::query()->whereKey($this->leadId)->update(['bitrix_synced_at' => now()]);
        }

        Log::info('[CRM] bitrix_synced_at actualizado.', [
            'funnel' => $this->funnel,
            'lead_id' => $this->leadId,
            'rows_updated' => $updated,
        ]);
    }

    /**
     * Incrementa errores de sync; si supera MAX_SYNC_FAILURES marca fallido permanente.
     *
     * @return bool true si el lead quedó marcado como fallido y no debe reintentarse
     */
    private function recordSyncFailure(\Throwable $exception): bool
    {
        $message = mb_substr($exception->getMessage(), 0, 500);
        $errors = $this->currentSyncErrorCount() + 1;

        $payload = [
            'bitrix_sync_errors' => $errors,
            'bitrix_sync_last_error' => $message,
        ];

        $permanentlyFailed = $errors >= self::MAX_SYNC_FAILURES;
        if ($permanentlyFailed) {
            $payload['bitrix_sync_failed_at'] = now();
        }

        $this->updateLeadSyncState($payload);

        if ($permanentlyFailed) {
            Log::critical('[CRM] Lead marcado como fallido tras superar reintentos de sincronización.', [
                'funnel' => $this->funnel,
                'lead_id' => $this->leadId,
                'bitrix_sync_errors' => $errors,
                'error' => $message,
            ]);
        }

        return $permanentlyFailed;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateLeadSyncState(array $payload): void
    {
        if ($this->funnel === 'consolidado') {
            LandingConsolidadoLead::query()->whereKey($this->leadId)->update($payload);
        } elseif ($this->funnel === 'curso') {
            LandingCursoLead::query()->whereKey($this->leadId)->update($payload);
        }
    }

    private function markBitrixFailed(?string $lastError = null): void
    {
        $payload = ['bitrix_sync_failed_at' => now()];
        if ($lastError !== null && $lastError !== '') {
            $payload['bitrix_sync_last_error'] = mb_substr($lastError, 0, 500);
        }
        $this->updateLeadSyncState($payload);
    }

    private function currentSyncErrorCount(): int
    {
        $lead = $this->findLeadModel();

        return $lead ? (int) $lead->bitrix_sync_errors : 0;
    }

    public function failed(\Throwable $exception): void
    {
        $lead = $this->findLeadModel();
        if ($lead !== null && $lead->bitrix_sync_failed_at === null && (int) $lead->bitrix_sync_errors < self::MAX_SYNC_FAILURES) {
            $this->recordSyncFailure($exception);
        }

        Log::critical('[CRM] Sincronización landing → CRM falló tras reintentos de cola.', [
            'funnel' => $this->funnel,
            'lead_id' => $this->leadId,
            'bitrix_sync_errors' => $this->currentSyncErrorCount(),
            'error' => $exception->getMessage(),
        ]);
    }
}
