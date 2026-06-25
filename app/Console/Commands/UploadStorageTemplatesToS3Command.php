<?php

namespace App\Console\Commands;

use App\Support\Storage\StoragePathSanitizer;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Sube plantillas de storage/app/public/templates/ al bucket S3 (misma ruta relativa en BD/código).
 */
class UploadStorageTemplatesToS3Command extends Command
{
    protected $signature = 'storage:upload-templates-to-s3
        {--dry-run : Solo listar archivos}
        {--force : Sobrescribir si ya existe en S3}';

    protected $description = 'Sube plantillas (CONSIDERATIONS, excel-confirmacion, etc.) de storage/app/public/templates/ a S3';

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

        $root = storage_path('app/public/templates');
        if (!is_dir($root)) {
            $this->error('No existe el directorio: ' . $root);

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $uploaded = 0;
        $skipped = 0;
        $failed = 0;

        $this->info("Bucket: {$bucket}");
        $this->line('Destino: templates/ en la raíz del bucket (sin AWS_UPLOAD_PREFIX)');
        $this->newLine();

        $finder = new Finder();
        $finder->files()->in($root);

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $localRelative = str_replace('\\', '/', $file->getRelativePathname());
            $relative = StoragePathSanitizer::relativePath('templates/' . $localRelative);
            $absolute = $file->getPathname();

            if (!$force && $client->doesObjectExist($bucket, $relative)) {
                $this->line("Omitido (ya existe en bucket): {$relative}");
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("[dry-run] Falta/subiría: {$relative}" . ($localRelative !== substr($relative, strlen('templates/')) ? " (local: {$localRelative})" : ''));
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
                    'Key' => $relative,
                    'Body' => $stream,
                ]);
                $this->info("Subido: {$relative}");
                $uploaded++;
            } catch (\Throwable $e) {
                $this->error("Fallo {$relative}: " . $e->getMessage());
                $failed++;
            } finally {
                if (isset($stream) && is_resource($stream)) {
                    fclose($stream);
                }
            }
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
