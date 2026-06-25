<?php

namespace App\Console\Commands;

use App\Support\Storage\StoragePathSanitizer;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Sube archivos de storage/app/public/{templates,boletas,...} al bucket S3
 * con la MISMA clave (ruta + nombre normalizado) que la BD/código esperan.
 */
class UploadStorageTemplatesToS3Command extends Command
{
    protected $signature = 'storage:upload-templates-to-s3
        {--dir=* : Subcarpeta(s) bajo storage/app/public a procesar (default: templates, boletas)}
        {--file= : Archivo específico (relativo a la subcarpeta) a procesar}
        {--dry-run : Solo listar archivos}
        {--force : Sobrescribir si ya existe en S3}';

    protected $description = 'Sube archivos de storage/app/public/{templates,boletas} a S3 con la clave normalizada que usa la BD';

    /** Subcarpetas por defecto si no se pasa --dir */
    private const DEFAULT_DIRS = ['templates', 'boletas'];

    public function handle(): int
    {
        if ($this->uploadDisk() !== 's3') {
            $this->error('FILESYSTEM_UPLOAD_DISK debe ser "s3". Actual: ' . $this->uploadDisk());

            return self::FAILURE;
        }

        $bucket = (string) config('filesystems.disks.s3.bucket', '');
        $client = $this->s3Client();
        if ($bucket === '' || $client === null) {
            $this->error('S3 no está configurado correctamente (cliente o bucket vacío).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $onlyFile = $this->normalizedFileOption();
        $dirs = $this->dirsToProcess();

        $uploaded = 0;
        $skipped = 0;
        $failed = 0;
        $matchedFile = false;

        $this->info("Bucket: {$bucket}");
        $this->line('Destino: raíz del bucket (sin AWS_UPLOAD_PREFIX). Carpetas: ' . implode(', ', $dirs));
        $this->newLine();

        foreach ($dirs as $dir) {
            $root = storage_path('app/public/' . $dir);
            if (!is_dir($root)) {
                $this->warn("Omitiendo carpeta inexistente: storage/app/public/{$dir}");

                continue;
            }

            $finder = new Finder();
            $finder->files()->in($root);

            /** @var SplFileInfo $file */
            foreach ($finder as $file) {
                $localRelative = str_replace('\\', '/', $file->getRelativePathname());

                if ($onlyFile !== null && StoragePathSanitizer::relativePath($localRelative) !== $onlyFile) {
                    continue;
                }
                $matchedFile = true;

                $key = StoragePathSanitizer::relativePath($dir . '/' . $localRelative);
                $absolute = $file->getPathname();

                if (!$force && $client->doesObjectExist($bucket, $key)) {
                    $this->line("Omitido (ya existe en bucket): {$key}");
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $renamed = $localRelative !== substr($key, strlen($dir) + 1);
                    $this->line("[dry-run] Falta/subiría: {$key}" . ($renamed ? " (local: {$dir}/{$localRelative})" : ''));
                    $this->line('  CDN: ' . $this->cdnUrl($key));
                    $uploaded++;

                    continue;
                }

                try {
                    $stream = fopen($absolute, 'rb');
                    if ($stream === false) {
                        throw new \RuntimeException('No se pudo abrir: ' . $absolute);
                    }
                    $client->putObject([
                        'Bucket' => $bucket,
                        'Key' => $key,
                        'Body' => $stream,
                    ]);
                    $this->info("Subido: {$key}");
                    $this->line('  CDN: ' . $this->cdnUrl($key));
                    $uploaded++;
                } catch (\Throwable $e) {
                    $this->error("Fallo {$key}: " . $e->getMessage());
                    $failed++;
                } finally {
                    if (isset($stream) && is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }
        }

        if ($onlyFile !== null && !$matchedFile) {
            $this->error('No se encontró el archivo local solicitado en ' . implode('/, ', $dirs) . '/: ' . $onlyFile);
            $failed++;
        }

        $this->newLine();
        $this->table(['Métrica', 'Cantidad'], [
            ['Subidos / listados', $uploaded],
            ['Omitidos', $skipped],
            ['Errores', $failed],
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function uploadDisk(): string
    {
        return (string) config('object_storage.upload_disk', 'local');
    }

    /**
     * @return string[]
     */
    private function dirsToProcess(): array
    {
        $dirs = (array) $this->option('dir');
        $dirs = array_filter(array_map(static function ($dir) {
            return trim(str_replace('\\', '/', (string) $dir), '/');
        }, $dirs));

        if ($dirs === []) {
            $dirs = self::DEFAULT_DIRS;
        }

        return array_values(array_unique($dirs));
    }

    private function normalizedFileOption(): ?string
    {
        $file = $this->option('file');
        if (!is_string($file) || trim($file) === '') {
            return null;
        }

        $clean = str_replace('\\', '/', trim($file));
        $clean = preg_replace('#^(templates|boletas)/#', '', $clean);

        return StoragePathSanitizer::relativePath($clean);
    }

    private function cdnUrl(string $relative): string
    {
        $base = rtrim((string) config('object_storage.cdn_base_url', ''), '/');

        return ($base !== '' ? $base : 'https://cdn.probusiness.pe') . '/' . ltrim($relative, '/');
    }

    /**
     * @return \Aws\S3\S3Client|null
     */
    private function s3Client()
    {
        try {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('s3');
            $adapter = $disk->getAdapter();
            if (method_exists($adapter, 'getClient')) {
                return $adapter->getClient();
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}
