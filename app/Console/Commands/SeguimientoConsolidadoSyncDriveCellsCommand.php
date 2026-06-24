<?php

namespace App\Console\Commands;

use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveCellSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeguimientoConsolidadoSyncDriveCellsCommand extends Command
{
    protected $signature = 'segimiento-consolidado:sync-drive-cells
                            {--id= : Solo este consolidado}
                            {--all : Todos los vinculados a Drive}
                            {--dry-run : Listar sin sincronizar}';

    protected $description = 'Lee el Excel actual en Drive y actualiza celdas + historial en BD (preserva notas manuales)';

    public function handle(SeguimientoConsolidadoDriveCellSyncService $syncService): int
    {
        $ids = $this->resolveContenedorIds();
        if ($ids === []) {
            $this->warn('No hay consolidados vinculados para sincronizar.');

            return self::SUCCESS;
        }

        $this->info('Consolidados a sincronizar: ' . count($ids));

        if ($this->option('dry-run')) {
            foreach ($ids as $id) {
                $this->line('  - ID ' . $id);
            }

            return self::SUCCESS;
        }

        $ok = 0;
        $failed = 0;

        foreach ($ids as $idContenedor) {
            $result = $syncService->pullFromDrive((int) $idContenedor, 'command');
            if (empty($result['success'])) {
                $failed++;
                $this->error(sprintf(
                    'ID %d: %s',
                    $idContenedor,
                    $result['message'] ?? 'error desconocido'
                ));

                continue;
            }

            $ok++;
            $this->line(sprintf(
                'ID %d: %d celdas, %d cambios en historial',
                $idContenedor,
                (int) ($result['cells_upserted'] ?? 0),
                (int) ($result['cells_history'] ?? 0)
            ));
        }

        $this->info("Completado: {$ok} OK, {$failed} fallidos");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return int[]
     */
    private function resolveContenedorIds(): array
    {
        $id = $this->option('id');
        if ($id !== null && $id !== '') {
            return [(int) $id];
        }

        if (!$this->option('all')) {
            $this->error('Indique --id= o --all');

            return [];
        }

        return DB::table('carga_consolidada_contenedor')
            ->whereNotNull('excel_seguimiento_drive_link')
            ->whereNotNull('excel_seguimiento_drive_file_id')
            ->whereNotNull('f_inicio')
            ->orderBy('id')
            ->pluck('id')
            ->map(function ($value) {
                return (int) $value;
            })
            ->all();
    }
}
