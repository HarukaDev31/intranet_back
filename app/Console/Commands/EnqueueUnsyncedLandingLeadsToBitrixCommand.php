<?php

namespace App\Console\Commands;

use App\Jobs\Crm\SyncLandingLeadToCrmJob;
use App\Models\LandingConsolidadoLead;
use App\Models\LandingCursoLead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Reencola sincronización Bitrix para leads cuyo job falló o nunca terminó.
 * El schedule debe ejecutarse cada minuto cuando BITRIX_WEBHOOK_URL esté definido.
 */
class EnqueueUnsyncedLandingLeadsToBitrixCommand extends Command
{
    protected $signature = 'landing:enqueue-bitrix-sync';

    protected $description = 'Encola SyncLandingLeadToCrmJob para leads landing sin bitrix_synced_at';

    public function handle(): int
    {
        Log::info('[CRM][Cron] Inicio landing:enqueue-bitrix-sync');

        $webhook = config('services.bitrix.webhook_url');
        if (empty($webhook) || !is_string($webhook)) {
            $this->line('Bitrix webhook no configurado; nada que encolar.');
            Log::info('[CRM][Cron] Omitido por webhook no configurado');

            return 0;
        }

        $consolidadoIds = LandingConsolidadoLead::query()
            ->whereNull('bitrix_synced_at')
            ->orderBy('id')
            ->pluck('id');

        $dispatchedConsolidado = 0;
        foreach ($consolidadoIds as $id) {
            SyncLandingLeadToCrmJob::dispatch('consolidado', (int) $id);
            $dispatchedConsolidado++;
        }

        $cursoEnabled = (bool) (config('landing_curso.bitrix.enabled') ?? true);
        $dispatchedCurso = 0;
        if ($cursoEnabled) {
            $cursoIds = LandingCursoLead::query()
                ->whereNull('bitrix_synced_at')
                ->orderBy('id')
                ->pluck('id');

            foreach ($cursoIds as $id) {
                SyncLandingLeadToCrmJob::dispatch('curso', (int) $id);
                $dispatchedCurso++;
            }
        }

        Log::info('[CRM][Cron] Resultado de barrido', [
            'pending_consolidado' => $consolidadoIds->count(),
            'pending_curso' => isset($cursoIds) ? $cursoIds->count() : 0,
            'curso_enabled' => $cursoEnabled,
            'dispatched_consolidado' => $dispatchedConsolidado,
            'dispatched_curso' => $dispatchedCurso,
        ]);

        if ($dispatchedConsolidado || $dispatchedCurso) {
            $this->info("Encolados: consolidado={$dispatchedConsolidado}, curso={$dispatchedCurso}");
        } else {
            $this->line('Sin leads pendientes de sincronizar Bitrix.');
        }

        return 0;
    }
}
