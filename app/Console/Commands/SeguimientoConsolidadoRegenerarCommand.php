<?php

namespace App\Console\Commands;

use App\Models\CargaConsolidada\Contenedor;
use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;
use App\Services\CargaConsolidada\SeguimientoConsolidadoVincularEligibility;
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

        if (!SeguimientoConsolidadoVincularEligibility::puedeOperarSeguimientoDrive($contenedor)) {
            $this->error(
                SeguimientoConsolidadoVincularEligibility::tieneFInicio($contenedor)
                    ? 'Consolidado fuera de alcance: ' . SeguimientoConsolidadoVincularEligibility::describeRegla($contenedor)
                    : SeguimientoConsolidadoVincularEligibility::mensajeSinFInicio()
            );

            return 1;
        }

        // Ya vinculado: sync (pull notas → regenerar → subir). Sin link: vincular inicial.
        $yaVinculado = !empty($contenedor->excel_seguimiento_drive_link)
            || !empty($contenedor->excel_seguimiento_drive_file_id);

        if ($this->option('sync')) {
            $result = $yaVinculado
                ? $service->executeSync($idContenedor)
                : $service->executeVincular($idContenedor);
        } else {
            if ($yaVinculado) {
                $service->enqueueSyncJob($idContenedor, 'regenerar_manual');
                $result = [
                    'success' => true,
                    'queued' => true,
                    'message' => 'Sincronización de Excel encolada (preserva notas manuales).',
                    'data' => $service->formatStatusData($contenedor),
                ];
            } else {
                $result = $service->queueVincular($idContenedor);
            }
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
