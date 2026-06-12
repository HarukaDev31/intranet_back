<?php

namespace App\Console\Commands;

use App\Models\CargaConsolidada\Contenedor;
use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;
use App\Services\CargaConsolidada\SeguimientoConsolidadoVincularEligibility;
use Illuminate\Console\Command;

class SeguimientoConsolidadoVincularCommand extends Command
{
    protected $signature = 'segimiento-consolidado:vincular
                            {idContenedor? : ID del consolidado (omitir = todos los elegibles pendientes)}
                            {--sync : Ejecutar sin cola (solo con idContenedor)}';

    protected $description = 'Vincula Excel seguimiento a Drive (#11-2026 en adelante). Sin ID: cron encola pendientes.';

    /**
     * @param SeguimientoConsolidadoDriveService $service
     * @return int
     */
    public function handle(SeguimientoConsolidadoDriveService $service)
    {
        $idContenedor = $this->argument('idContenedor');

        if ($idContenedor !== null) {
            return $this->vincularUno($service, (int) $idContenedor);
        }

        if ($this->option('sync')) {
            $this->error('--sync solo aplica con idContenedor.');

            return 1;
        }

        $result = $service->queueVincularPendientes();

        if ($result['encolados'] === 0) {
            $this->info('No hay consolidados elegibles pendientes de vincular.');

            return 0;
        }

        $this->info(sprintf(
            'Encolados %d job(s) de vinculación (%d elegible(s)).',
            $result['encolados'],
            $result['total']
        ));
        $this->line('IDs: ' . implode(', ', $result['ids']));

        return 0;
    }

    /**
     * @param SeguimientoConsolidadoDriveService $service
     * @param int $idContenedor
     * @return int
     */
    private function vincularUno(SeguimientoConsolidadoDriveService $service, $idContenedor)
    {
        $contenedor = Contenedor::find($idContenedor);

        if (!$contenedor) {
            $this->error('Consolidado no encontrado.');

            return 1;
        }

        if (!empty($contenedor->excel_seguimiento_drive_link)) {
            $this->error('Ya está vinculado. Use segimiento-consolidado:regenerar para crear un nuevo Excel.');

            return 1;
        }

        if (!SeguimientoConsolidadoVincularEligibility::cumpleUmbralCarga($contenedor)) {
            $this->error('No cumple la regla de vinculación: ' . SeguimientoConsolidadoVincularEligibility::describeRegla($contenedor));

            return 1;
        }

        if ($this->option('sync')) {
            $result = $service->executeVincular($idContenedor);
        } else {
            $result = $service->queueVincular($idContenedor);
        }

        if (empty($result['success'])) {
            $this->error($result['message'] ?? 'No se pudo vincular el Excel a Drive.');

            return 1;
        }

        $this->info($result['message'] ?? 'Vinculación iniciada correctamente.');
        if (!empty($result['queued'])) {
            $this->line('Job encolado en la cola carga_consolidada.');
        }
        if (!empty($result['data']['drive_link'])) {
            $this->line('Drive: ' . $result['data']['drive_link']);
        }

        return 0;
    }
}
