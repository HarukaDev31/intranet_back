<?php

namespace App\Console\Commands;

use App\Models\CargaConsolidada\Contenedor;
use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;
use Illuminate\Console\Command;

class SeguimientoConsolidadoRegenerarCommand extends Command
{
    protected $signature = 'segimiento-consolidado:regenerar
                            {idContenedor : ID del consolidado}
                            {--sync : Ejecutar sin cola}';

    protected $description = 'Regenera manualmente el Excel de seguimiento en Drive (no usa cron ni reglas de auto-vincular)';

    /**
     * @param SeguimientoConsolidadoDriveService $service
     * @return int
     */
    public function handle(SeguimientoConsolidadoDriveService $service)
    {
        $idContenedor = (int) $this->argument('idContenedor');
        $contenedor = Contenedor::find($idContenedor);

        if (!$contenedor) {
            $this->error('Consolidado no encontrado.');

            return 1;
        }

        if ($this->option('sync')) {
            $result = $service->executeVincular($idContenedor);
        } else {
            $result = $service->queueVincular($idContenedor);
        }

        if (empty($result['success'])) {
            $this->error($result['message'] ?? 'No se pudo regenerar el Excel en Drive.');

            return 1;
        }

        $this->info($result['message'] ?? 'Regeneración iniciada correctamente.');
        if (!empty($result['queued'])) {
            $this->line('Job encolado en la cola carga_consolidada.');
        }
        if (!empty($result['data']['drive_link'])) {
            $this->line('Drive: ' . $result['data']['drive_link']);
        }

        return 0;
    }
}
