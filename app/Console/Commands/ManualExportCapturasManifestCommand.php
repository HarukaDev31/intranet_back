<?php

namespace App\Console\Commands;

use App\Services\ManualUsuario\ManualUsuarioCapturasInventory;
use Illuminate\Console\Command;

class ManualExportCapturasManifestCommand extends Command
{
    protected $signature = 'manual:export-capturas-manifest
                            {--output=resources/manual/capturas/manifest.json : Ruta JSON de salida compatible con el runner}
                            {--strict : Falla si faltan ruta, capture_key o configuración runner válida}';

    protected $description = 'Exporta el inventario versionable de capturas y pasos del Manual de Usuario';

    public function handle(ManualUsuarioCapturasInventory $inventory)
    {
        $manifest = $inventory->build();
        $issues = $inventory->validateRunnerManifest($inventory->toRunnerManifest($manifest));
        if ($this->option('strict') && $issues) {
            $this->error('No se exportó: el manifiesto no cumple el contrato del runner.');
            foreach ($issues as $issue) {
                $this->line(' - ' . $issue);
            }

            return self::FAILURE;
        }

        $path = $this->absolutePath((string) $this->option('output'));
        $inventory->write($path, $manifest);
        $this->info('Manifiesto runner escrito en ' . $path . ' ('
            . count($manifest['captures']) . ' capturas).');
        if ($issues) {
            $this->warn(count($issues) . ' incidencia(s); usa --strict antes de ejecutar Playwright.');
        }

        return self::SUCCESS;
    }

    private function absolutePath(string $path): string
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) ? $path : base_path($path);
    }
}
