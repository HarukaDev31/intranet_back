<?php

namespace App\Console\Commands;

use App\Enums\CargaConsolidada\ExcelSeguimientoLinkStatus;
use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;
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
    public function handle(SeguimientoConsolidadoDriveService $driveService)
    {
        $ids = DB::table('carga_consolidada_contenedor')
            ->whereNotNull('excel_seguimiento_drive_link')
            ->whereNotNull('f_inicio')
            ->where(function ($q) {
                $q->whereNull('excel_seguimiento_link_status')
                    ->orWhereNotIn('excel_seguimiento_link_status', [
                        ExcelSeguimientoLinkStatus::QUEUED,
                        ExcelSeguimientoLinkStatus::PROCESSING,
                    ]);
            })
            ->pluck('id');

        $encolados = 0;
        $omitidos = 0;
        $idsEncolados = [];

        foreach ($ids as $id) {
            if ($driveService->enqueueSyncJob((int) $id, 'scheduler_backup')) {
                $encolados++;
                $idsEncolados[] = (int) $id;
            } else {
                $omitidos++;
            }
        }

        Log::info('[SeguimientoDrive] Scheduler sync-linked encoló jobs', [
            'total_vinculados' => $ids->count(),
            'encolados' => $encolados,
            'omitidos_debounce' => $omitidos,
            'ids_encolados' => $idsEncolados,
        ]);

        $this->info('Encolados ' . $encolados . ' job(s) de sincronización (' . $omitidos . ' omitidos por debounce).');

        return 0;
    }
}
