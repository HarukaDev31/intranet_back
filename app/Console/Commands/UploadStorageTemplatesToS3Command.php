<?php

namespace App\Console\Commands;

use App\Contracts\ObjectStorageConnectorInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Sube plantillas de storage/app/public/templates/ al bucket S3 (misma ruta relativa en BD/código).
 */
class UploadStorageTemplatesToS3Command extends Command
{
    protected $signature = 'storage:upload-templates-to-s3
        {--dry-run : Solo listar archivos}
        {--force : Sobrescribir si ya existe en S3}';

    protected $description = 'Sube plantillas (CONSIDERATIONS, excel-confirmacion, etc.) de storage/app/public/templates/ a S3';

    /** @var ObjectStorageConnectorInterface */
    private $storage;

    public function handle(ObjectStorageConnectorInterface $storage): int
    {
        $this->storage = $storage;

        if ($this->uploadDisk() !== 's3') {
            $this->error('FILESYSTEM_UPLOAD_DISK debe ser "s3". Actual: ' . $this->uploadDisk());

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

        $finder = new Finder();
        $finder->files()->in($root);

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $relative = 'templates/' . str_replace('\\', '/', $file->getRelativePathname());
            $absolute = $file->getPathname();

            if (!$force && Storage::disk('s3')->exists($relative)) {
                $this->line("Omitido (ya en S3): {$relative}");
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("[dry-run] {$relative}");
                $uploaded++;

                continue;
            }

            try {
                $stream = fopen($absolute, 'rb');
                if ($stream === false) {
                    throw new \RuntimeException('No se pudo abrir: ' . $absolute);
                }
                $ok = Storage::disk('s3')->writeStream($relative, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if (!$ok) {
                    throw new \RuntimeException('writeStream devolvió false');
                }
                $this->info("Subido: {$relative}");
                $uploaded++;
            } catch (\Throwable $e) {
                $this->error("Fallo {$relative}: " . $e->getMessage());
                $failed++;
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
}
