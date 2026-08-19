<?php

namespace App\Console\Commands;

use App\Models\CargaConsolidada\Contenedor;
use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveCellRepository;
use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;
use Illuminate\Console\Command;

class SeguimientoConsolidadoRestoreUrgenciaNotasCommand extends Command
{
    protected $signature = 'segimiento-consolidado:restore-urgencia-notas
                            {idContenedor : ID del consolidado}
                            {--sync : Regenerar Excel en Drive tras restaurar}';

    protected $description = 'Restaura notas manuales de CONTACTAR CON URGENCIA desde historial y opcionalmente resincroniza Drive';

    public function handle(
        SeguimientoConsolidadoDriveCellRepository $repository,
        SeguimientoConsolidadoDriveService $driveService
    ): int {
        $idContenedor = (int) $this->argument('idContenedor');
        $contenedor = Contenedor::find($idContenedor);

        if (!$contenedor) {
            $this->error('Consolidado no encontrado.');

            return self::FAILURE;
        }

        $result = $repository->restoreManualNotesFromHistory(
            $idContenedor,
            'Seguimiento',
            'urgencia_notas'
        );

        $this->info(sprintf(
            'Restauradas %d notas (candidatas en historial: %d).',
            $result['restored'],
            $result['candidates']
        ));

        if ($result['candidates'] === 0) {
            $this->warn('No hay historial de notas URGENCIA para este consolidado.');

            return self::SUCCESS;
        }

        if (!$this->option('sync')) {
            $this->comment('Ejecuta con --sync para volver a escribirlas en el Excel de Drive.');

            return self::SUCCESS;
        }

        if (empty($contenedor->excel_seguimiento_drive_file_id)) {
            $this->error('El consolidado no tiene Excel vinculado en Drive.');

            return self::FAILURE;
        }

        $sync = $driveService->executeSync($idContenedor);
        if (empty($sync['success'])) {
            $this->error($sync['message'] ?? 'No se pudo resincronizar Drive.');

            return self::FAILURE;
        }

        $this->info($sync['message'] ?? 'Excel resincronizado en Drive.');

        return self::SUCCESS;
    }
}
