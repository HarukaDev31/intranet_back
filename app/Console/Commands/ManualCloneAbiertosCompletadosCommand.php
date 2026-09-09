<?php

namespace App\Console\Commands;

use App\Services\ManualUsuario\ManualUsuarioCloneAbiertosCompletados;
use Illuminate\Console\Command;
use RuntimeException;

class ManualCloneAbiertosCompletadosCommand extends Command
{
    protected $signature = 'manual:clone-abiertos-completados
                            {--dry-run : Inventaria y muestra el plan sin bajar ni enlazar}
                            {--source=cargaconsolidada/abiertos : Módulo origen}
                            {--target=cargaconsolidada/completados : Módulo destino}
                            {--cdn=https://cdn.probusiness.pe : Host público de producción para bajar PNG}
                            {--force : Reemplaza media ya enlazada en Completados (nunca toca Abiertos)}';

    protected $description = 'Clona capturas de Carga consolidada Abiertos a Completados (bytes desde CDN de prod, keys propias)';

    public function handle(ManualUsuarioCloneAbiertosCompletados $cloner)
    {
        try {
            $result = $cloner->clone([
                'dry_run' => (bool) $this->option('dry-run'),
                'source' => (string) $this->option('source'),
                'target' => (string) $this->option('target'),
                'cdn' => (string) $this->option('cdn'),
                'force' => (bool) $this->option('force'),
            ]);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $prefix = $result['dry_run'] ? 'Dry-run. ' : '';
        $this->info($prefix
            . 'Mapeados: ' . $result['mapped']
            . '. Descargados de prod: ' . $result['downloaded']
            . '. Enlazados: ' . $result['linked']
            . '. Sin equivalente: ' . $result['skipped_no_equivalent']
            . '. Ya tenían imagen: ' . $result['skipped_existing']
            . '. Fallidos: ' . $result['failed'] . '.');

        foreach (array_slice($result['links'], 0, 80) as $link) {
            $this->line(' - ' . $link['flow'] . ' #' . $link['step_number']
                . ' [' . ($link['match'] ?? '') . '] '
                . $link['source_title'] . ' → ' . $link['target_title']
                . ' | ' . $link['source_key'] . ' → ' . $link['target_key']
                . ($link['origin_url'] ? ' ← ' . $link['origin_url'] : '')
                . ' (' . ($link['status'] ?? '') . ')');
        }
        if (count($result['links']) > 80) {
            $this->line(' … ' . (count($result['links']) - 80) . ' enlaces más.');
        }
        foreach ($result['skipped'] as $skip) {
            $this->warn(' Sin equivalente: ' . $skip['flow'] . ' #' . $skip['step_number']
                . ' ' . $skip['step_title'] . ' [' . $skip['target_key'] . ']');
        }
        foreach ($result['errors'] as $error) {
            $this->error(' Falló: ' . ($error['target_key'] ?? '')
                . ' — ' . ($error['reason'] ?? ''));
        }
        if ($result['origin_urls']) {
            $this->info('Orígenes: ' . implode(', ', $result['origin_urls']));
        }

        return $result['failed'] > 0 && !$result['dry_run'] ? self::FAILURE : self::SUCCESS;
    }
}
