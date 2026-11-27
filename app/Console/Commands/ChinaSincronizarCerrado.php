<?php

namespace App\Console\Commands;

use App\Services\ChinaCaja\ChinaContainerService;
use Illuminate\Console\Command;

/**
 * Backfill y validación diaria de CERRADO (china_cerrado_at):
 * marca los consolidados COMPLETADO con las 3 etapas listas y limpia los que tengan una etapa vuelta atrás.
 */
class ChinaSincronizarCerrado extends Command
{
    /**
     * @var string
     */
    protected $signature = 'china:sincronizar-cerrado {--dry-run : Mostrar cuántos cambiaría sin guardar}';

    /**
     * @var string
     */
    protected $description = 'Alinea el estado CERRADO de los consolidados de China con el progreso de las 3 etapas';

    public function handle(ChinaContainerService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $resumen = $service->sincronizarCerrados($dryRun);

        $this->info(sprintf(
            '%s: revisados=%d, marcados=%d, limpiados=%d.',
            $dryRun ? 'Simulación (sin guardar)' : 'CERRADO sincronizado',
            $resumen['revisados'],
            $resumen['marcados'],
            $resumen['limpiados'],
        ));

        return self::SUCCESS;
    }
}
