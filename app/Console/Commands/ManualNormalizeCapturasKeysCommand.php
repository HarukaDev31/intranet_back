<?php

namespace App\Console\Commands;

use App\Services\ManualUsuario\ManualUsuarioCapturasNormalizer;
use Illuminate\Console\Command;

class ManualNormalizeCapturasKeysCommand extends Command
{
    protected $signature = 'manual:normalize-capturas-keys
                            {--dry-run : Muestra el plan sin escribir}';

    protected $description = 'Unifica capture_output de bloques media a {capture_key}.png sin borrar copy';

    public function handle(ManualUsuarioCapturasNormalizer $normalizer)
    {
        $result = $normalizer->normalize([
            'dry_run' => (bool) $this->option('dry-run'),
        ]);

        $prefix = $result['dry_run'] ? 'Dry-run. ' : '';
        $this->info($prefix . 'Actualizados: ' . $result['updated']
            . '. Sin cambio: ' . $result['unchanged']
            . '. Inválidos: ' . $result['invalid'] . '.');
        $this->info('Claves compartidas: ' . $result['shared_keys']
            . ' (' . $result['shared_blocks'] . ' bloques, '
            . $result['alias_blocks'] . ' aliasados).');

        foreach (array_slice($result['groups'], 0, 40) as $group) {
            $this->line(' - ' . $group['capture_key'] . ': ' . $group['blocks']
                . ' bloques [' . implode(', ', $group['roles']) . ']');
        }
        if (count($result['groups']) > 40) {
            $this->line(' … ' . (count($result['groups']) - 40) . ' grupos más.');
        }

        return self::SUCCESS;
    }
}
