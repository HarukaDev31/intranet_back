<?php

namespace App\Console\Commands;

use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;
use Illuminate\Console\Command;

class SeguimientoConsolidadoResetDriveCommand extends Command
{
    protected $signature = 'segimiento-consolidado:reset-drive
                            {idContenedor? : ID del consolidado (omitir con --all)}
                            {--all : Reset de todos los vinculados y purga archivos en carpeta raíz Drive}
                            {--keep-historico : Conservar cortes CONTACTAR y row_sync}
                            {--no-revincular : No encolar vincular al terminar}
                            {--force : Ejecutar sin confirmación}';

    protected $description = 'Borra Excel seguimiento en Drive, limpia vínculos en BD y re-vincula elegibles';

    /**
     * @param SeguimientoConsolidadoDriveService $service
     * @return int
     */
    public function handle(SeguimientoConsolidadoDriveService $service)
    {
        $idContenedor = $this->argument('idContenedor');
        $all = (bool) $this->option('all');

        if (!$all && $idContenedor === null) {
            $this->error('Indique idContenedor o use --all.');

            return 1;
        }

        if ($all && $idContenedor !== null) {
            $this->error('Use idContenedor o --all, no ambos.');

            return 1;
        }

        $scope = $all
            ? 'TODOS los consolidados vinculados + purga carpeta raíz en Drive'
            : 'consolidado #' . $idContenedor;

        $extras = [];
        if ($this->option('keep-historico')) {
            $extras[] = 'conserva histórico CONTACTAR';
        }
        if ($this->option('no-revincular')) {
            $extras[] = 'sin re-vincular';
        }

        $msg = '¿Reset seguimiento Drive (' . $scope . ')';
        if (!empty($extras)) {
            $msg .= ' [' . implode(', ', $extras) . ']';
        }
        $msg .= '?';

        if (!$this->option('force') && !$this->confirm($msg, false)) {
            $this->info('Operación cancelada.');

            return 0;
        }

        $result = $service->resetSeguimientoDrive(
            $idContenedor !== null ? (int) $idContenedor : null,
            $all,
            (bool) $this->option('keep-historico'),
            !$this->option('no-revincular')
        );

        if (empty($result['success'])) {
            $this->error($result['message'] ?? 'No se pudo completar el reset.');

            return 1;
        }

        $this->info($result['message']);
        $this->line('Consolidados afectados: ' . ($result['contenedores'] ?? 0));
        $this->line('Archivos borrados por file_id: ' . ($result['drive_deleted_by_id'] ?? 0));

        if ($all) {
            $this->line('Archivos purgados en carpeta raíz: ' . ($result['drive_purged'] ?? 0));
        }

        if (!empty($result['historico_limpiado'])) {
            $this->line('Histórico CONTACTAR / row_sync: limpiado');
        }

        if (!empty($result['drive_errors'])) {
            $this->warn('Errores Drive (' . count($result['drive_errors']) . '):');
            foreach ($result['drive_errors'] as $error) {
                $this->line('  - ' . $error);
            }
        }

        if (!empty($result['revincular_encolados'])) {
            $this->info('Jobs de vincular encolados: ' . $result['revincular_encolados']);
            if (!empty($result['revincular_ids'])) {
                $this->line('IDs: ' . implode(', ', $result['revincular_ids']));
            }
        } elseif (!$this->option('no-revincular')) {
            $this->line('No se encolaron vinculaciones (ningún elegible pendiente).');
        }

        return 0;
    }
}
