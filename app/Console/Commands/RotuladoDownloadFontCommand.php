<?php

namespace App\Console\Commands;

use App\Services\CargaConsolidada\RotuladoPdfService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RotuladoDownloadFontCommand extends Command
{
    protected $signature = 'rotulado:download-font
                            {--force : Volver a descargar aunque la fuente ya exista}';

    protected $description = 'Descarga Noto Sans SC para el PDF de rotulado (caracteres chinos en DomPDF)';

    /**
     * @return int
     */
    public function handle()
    {
        $destPath = RotuladoPdfService::fontAbsolutePath();
        $destDir = dirname($destPath);

        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            $this->error('No se pudo crear el directorio: ' . $destDir);

            return self::FAILURE;
        }

        if (RotuladoPdfService::fontInstalled() && !$this->option('force')) {
            $this->info('La fuente ya está instalada: ' . $destPath);
            $this->line('Tamaño: ' . $this->formatBytes((int) filesize($destPath)));
            $this->line('Use --force para volver a descargar.');

            return self::SUCCESS;
        }

        $url = RotuladoPdfService::FONT_DOWNLOAD_URL;
        $this->info('Descargando Noto Sans SC...');
        $this->line('URL: ' . $url);
        $this->line('Destino: ' . $destPath);

        try {
            $response = Http::timeout(300)
                ->withOptions(array(
                    'allow_redirects' => true,
                ))
                ->get($url);
        } catch (\Throwable $e) {
            $this->error('Error de red al descargar la fuente: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (!$response->successful()) {
            $this->error('Descarga fallida. HTTP ' . $response->status());

            return self::FAILURE;
        }

        $body = $response->body();
        $size = strlen($body);

        if ($size < 1000000) {
            $this->error('Archivo demasiado pequeño (' . $this->formatBytes($size) . '). ¿URL incorrecta o bloqueada?');

            return self::FAILURE;
        }

        if (file_put_contents($destPath, $body) === false) {
            $this->error('No se pudo escribir el archivo en: ' . $destPath);

            return self::FAILURE;
        }

        $this->info('Fuente instalada correctamente.');
        $this->line('Tamaño: ' . $this->formatBytes($size));
        $this->newLine();
        $this->line('En Docker: docker compose exec app php artisan rotulado:download-font');

        return self::SUCCESS;
    }

    /**
     * @param int $bytes
     * @return string
     */
    private function formatBytes($bytes)
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}
