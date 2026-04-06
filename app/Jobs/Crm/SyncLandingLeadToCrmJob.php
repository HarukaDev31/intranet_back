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

        $result = $adapter->sync($lead->toArray());

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

    public function failed(\Throwable $exception)
    {
        Log::critical('[CRM] Sincronización landing → CRM falló tras reintentos.', [
            'funnel' => $this->funnel,
            'lead_id' => $this->leadId,
            'error' => $exception->getMessage(),
        ]);
    }
}
