<?php

namespace App\Console\Commands;

use App\Services\ManualUsuario\ManualUsuarioCapturasAuditor;
use App\Services\ManualUsuario\ManualUsuarioCapturasInventory;
use Illuminate\Console\Command;
use RuntimeException;

class ManualAuditCapturasCommand extends Command
{
    protected $signature = 'manual:audit-capturas
                            {--manifest= : Manifiesto existente; si se omite usa el inventario actual de BD}
                            {--directory= : Directorio de PNG; por defecto resources/manual/capturas}
                            {--report= : Escribe además el reporte JSON en esta ruta}
                            {--minimum-width=800 : Ancho mínimo recomendado}
                            {--minimum-height=450 : Alto mínimo recomendado}
                            {--strict : Devuelve código 1 cuando hay errores de cobertura/integridad}';

    protected $description = 'Audita cobertura, PNG huérfanos, hashes, dimensiones y media_id del manual';

    public function handle(
        ManualUsuarioCapturasInventory $inventory,
        ManualUsuarioCapturasAuditor $auditor
    ) {
        try {
            $manifest = $this->loadManifest($inventory);
            $report = $auditor->audit(
                $manifest,
                $this->absolutePath(
                    $this->option('directory') ?: 'resources/manual/capturas'
                ),
                [
                    'minimum_width' => (int) $this->option('minimum-width'),
                    'minimum_height' => (int) $this->option('minimum-height'),
                ]
            );
            if ($this->option('report')) {
                $this->writeReport($this->absolutePath((string) $this->option('report')), $report);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $summary = $report['summary'];
        $this->info('Capturas: ' . $summary['captures']
            . '. PNG: ' . $summary['png_files']
            . '. Con media_id: ' . $summary['with_media_id']
            . '. Errores: ' . $summary['errors']
            . '. Advertencias: ' . $summary['warnings'] . '.');
        foreach ($report['issues'] as $issue) {
            $line = '[' . strtoupper($issue['severity']) . '] ' . $issue['code']
                . ' ' . json_encode(
                    array_diff_key($issue, ['severity' => true, 'code' => true]),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            $issue['severity'] === 'error' ? $this->error($line) : $this->warn($line);
        }

        return $this->option('strict') && !$report['ok'] ? self::FAILURE : self::SUCCESS;
    }

    private function loadManifest(ManualUsuarioCapturasInventory $inventory): array
    {
        if (!$this->option('manifest')) {
            return $inventory->build();
        }

        $path = $this->absolutePath((string) $this->option('manifest'));
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('No se pudo leer el manifiesto: ' . $path);
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    private function writeReport(string $path, array $report): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio: ' . $directory);
        }
        file_put_contents(
            $path,
            json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . PHP_EOL
        );
    }

    private function absolutePath(string $path): string
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) ? $path : base_path($path);
    }
}
