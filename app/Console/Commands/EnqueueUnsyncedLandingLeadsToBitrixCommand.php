<?php

namespace App\Console\Commands;

use App\Jobs\Crm\SyncLandingLeadToCrmJob;
use App\Models\LandingConsolidadoLead;
use App\Models\LandingCursoLead;
use App\Support\Phone\PeruPhoneFormatter;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
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

        $pendingConsolidado = LandingConsolidadoLead::query()
            ->pendingBitrixSync()
            ->count();

        $dispatchedConsolidado = 0;
        foreach (
            LandingConsolidadoLead::query()
                ->pendingBitrixSync()
                ->orderBy('id')
                ->cursor() as $lead
        ) {
            $this->normalizeWhatsappForBitrix($lead, 'consolidado');
            SyncLandingLeadToCrmJob::dispatch('consolidado', (int) $lead->id);
            $dispatchedConsolidado++;
        }

        $cursoEnabled = (bool) (config('landing_curso.bitrix.enabled') ?? true);
        $dispatchedCurso = 0;
        $pendingCurso = 0;
        if ($cursoEnabled) {
            $pendingCurso = LandingCursoLead::query()
                ->pendingBitrixSync()
                ->count();

            foreach (
                LandingCursoLead::query()
                    ->pendingBitrixSync()
                    ->orderBy('id')
                    ->cursor() as $lead
            ) {
                $this->normalizeWhatsappForBitrix($lead, 'curso');
                SyncLandingLeadToCrmJob::dispatch('curso', (int) $lead->id);
                $dispatchedCurso++;
            }
        }

        Log::info('[CRM][Cron] Resultado de barrido', [
            'pending_consolidado' => $pendingConsolidado,
            'pending_curso' => $pendingCurso,
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

    /**
     * Normaliza el WhatsApp a E.164 (Perú) y lo guarda antes de encolar el job,
     * para que Bitrix reciba el mismo formato que usa duplicate.findbycomm.
     *
     * @param  LandingConsolidadoLead|LandingCursoLead  $lead
     */
    private function normalizeWhatsappForBitrix(Model $lead, string $funnel): void
    {
        $raw = trim((string) ($lead->whatsapp ?? ''));
        if ($raw === '') {
            return;
        }

        $canonical = PeruPhoneFormatter::toE164($raw);
        if ($canonical === '') {
            Log::warning('[CRM][Cron] WhatsApp no normalizable; se deja valor original.', [
                'funnel' => $funnel,
                'lead_id' => $lead->id,
                'whatsapp_raw' => mb_substr($raw, 0, 32),
            ]);

            return;
        }

        if ($lead->whatsapp === $canonical) {
            return;
        }

        $lead->forceFill(['whatsapp' => $canonical])->saveQuietly();

        Log::info('[CRM][Cron] WhatsApp normalizado antes de encolar Bitrix.', [
            'funnel' => $funnel,
            'lead_id' => $lead->id,
            'antes' => mb_substr($raw, 0, 32),
            'despues' => $canonical,
        ]);
    }
}
