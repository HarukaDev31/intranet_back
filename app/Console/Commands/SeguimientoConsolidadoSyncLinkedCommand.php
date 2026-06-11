<?php

namespace App\Console\Commands;

use App\Enums\CargaConsolidada\ExcelSeguimientoLinkStatus;
use App\Jobs\SyncSeguimientoConsolidadoExcelJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeguimientoConsolidadoSyncLinkedCommand extends Command
{
    protected $signature = 'segimiento-consolidado:sync-linked';

    protected $description = 'Encola sincronización de Excel en Drive para consolidados vinculados';

    /**
     * @return int
     */
    public function handle()
    {
        $ids = DB::table('carga_consolidada_contenedor')
            ->whereNotNull('excel_seguimiento_drive_link')
            ->where(function ($q) {
                $q->whereNull('excel_seguimiento_link_status')
                    ->orWhereNotIn('excel_seguimiento_link_status', [
                        ExcelSeguimientoLinkStatus::QUEUED,
                        ExcelSeguimientoLinkStatus::PROCESSING,
                    ]);
            })
            ->pluck('id');

        foreach ($ids as $id) {
            SyncSeguimientoConsolidadoExcelJob::dispatch((int) $id);
        }

        Log::info('[SeguimientoDrive] Scheduler sync-linked encoló jobs', [
            'total' => $ids->count(),
            'ids' => $ids->values()->all(),
        ]);

        $this->info('Encolados ' . $ids->count() . ' job(s) de sincronización.');

        return 0;
    }
}
