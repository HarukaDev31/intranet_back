<?php

namespace App\Console\Commands;

use App\Contracts\ObjectStorageConnectorInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class MigrateLocalStorageToS3Command extends Command
{
    protected $signature = 'storage:migrate-local-to-s3
        {--dry-run : Listar archivos sin subir}
        {--force : Sobrescribir objetos que ya existen en S3}
        {--source=both : Origen legacy: both, local o public}
        {--include-temp : Incluir carpetas temp y artefactos de proceso}
        {--subdir= : Solo migrar bajo esta ruta relativa (ej. cargaconsolidada/pagos)}
        {--limit=0 : Máximo de archivos a procesar (0 = sin límite)}';

    protected $description = 'Copia archivos legacy de storage/app y storage/app/public al bucket S3 (misma ruta relativa que en BD)';

    /** @var ObjectStorageConnectorInterface */
    private $storage;

    /** @var array<string, bool> */
    private $seenRelativePaths = [];

    /** @var int */
    private $uploaded = 0;

    /** @var int */
    private $skippedExisting = 0;

    /** @var int */
    private $skippedExcluded = 0;

    /** @var int */
    private $skippedDuplicate = 0;

    /** @var int */
    private $failed = 0;

    public function handle(ObjectStorageConnectorInterface $storage): int
    {
        $this->storage = $storage;

        if ($this->uploadDisk() !== 's3') {
            $this->error('FILESYSTEM_UPLOAD_DISK debe ser "s3". Valor actual: ' . $this->uploadDisk());

            return self::FAILURE;
        }

        if (!config('filesystems.disks.s3.bucket')) {
            $this->error('AWS_BUCKET no está configurado en .env');

            return self::FAILURE;
        }

        try {
            Storage::disk('s3')->directories('/');
        } catch (\Throwable $e) {
            $this->error('No se pudo conectar al bucket S3: ' . $e->getMessage());

            return self::FAILURE;
        }

        $source = (string) $this->option('source');
        if (!in_array($source, ['both', 'local', 'public'], true)) {
            $this->error('--source debe ser: both, local o public');

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $subdirFilter = $this->normalizeSubdirFilter((string) $this->option('subdir'));
        $includeTemp = (bool) $this->option('include-temp');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info('Migración legacy → S3');
        $this->line('Bucket: ' . config('filesystems.disks.s3.bucket'));
        $this->line('Prefijo S3: ' . ($this->s3Prefix() !== '' ? $this->s3Prefix() : '(raíz del bucket)'));
        $this->line('Origen local (disco legacy): ' . storage_path('app'));
        $this->line('Origen public (disco legacy public): ' . storage_path('app/public'));
        if ($dryRun) {
            $this->warn('Modo dry-run: no se subirá nada.');
        }

        $files = [];

        if ($source === 'both' || $source === 'public') {
            $files = array_merge($files, $this->collectFiles(storage_path('app/public'), 'public', $subdirFilter, $includeTemp));
        }

        if ($source === 'both' || $source === 'local') {
            $files = array_merge($files, $this->collectFiles(storage_path('app'), 'local', $subdirFilter, $includeTemp));
        }

        $total = count($files);
        $this->info("Archivos candidatos: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        foreach ($files as $file) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $this->processFile($file, $dryRun, $force);
            $bar->advance();
            $processed++;
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Subidos', $this->uploaded],
                ['Ya existían en S3 (omitidos)', $this->skippedExisting],
                ['Excluidos (temp/reglas)', $this->skippedExcluded],
                ['Duplicados local/public (omitidos)', $this->skippedDuplicate],
                ['Errores', $this->failed],
            ]
        );

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array{absolute: string, relative: string, origin: string}>
     */
    private function collectFiles(string $root, string $origin, ?string $subdirFilter, bool $includeTemp): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $absolute = $fileInfo->getPathname();
            $relative = $this->relativePathFromRoot($root, $absolute, $origin);

            if ($relative === null) {
                $this->skippedExcluded++;

                continue;
            }

            if ($subdirFilter !== null && stripos($relative, $subdirFilter) !== 0) {
                continue;
            }

            if (!$includeTemp && $this->isExcludedRelativePath($relative, $origin)) {
                $this->skippedExcluded++;

                continue;
            }

            if (isset($this->seenRelativePaths[$relative])) {
                $this->skippedDuplicate++;

                continue;
            }

            $this->seenRelativePaths[$relative] = true;
            $files[] = [
                'absolute' => $absolute,
                'relative' => $relative,
                'origin' => $origin,
            ];
        }

        return $files;
    }

    /**
     * @param array{absolute: string, relative: string, origin: string} $file
     */
    private function processFile(array $file, bool $dryRun, bool $force): void
    {
        $relative = $file['relative'];

        try {
            if (!$force && Storage::disk('s3')->exists($relative)) {
                $this->skippedExisting++;

                return;
            }

            if ($dryRun) {
                $this->uploaded++;

                return;
            }

            $stream = fopen($file['absolute'], 'rb');
            if ($stream === false) {
                throw new \RuntimeException('No se pudo abrir: ' . $file['absolute']);
            }

            $ok = Storage::disk('s3')->writeStream($relative, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (!$ok) {
                throw new \RuntimeException('writeStream devolvió false');
            }

            $this->uploaded++;
        } catch (\Throwable $e) {
            $this->failed++;
            $this->newLine();
            $this->error("Fallo {$relative}: " . $e->getMessage());
        }
    }

    private function relativePathFromRoot(string $root, string $absolute, string $origin): ?string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $absolute = str_replace('\\', '/', $absolute);

        if (stripos($absolute, $root . '/') !== 0 && $absolute !== $root) {
            return null;
        }

        $relative = ltrim(substr($absolute, strlen($root)), '/');

        if ($origin === 'local') {
            if ($relative === 'public' || stripos($relative, 'public/') === 0) {
                return null;
            }
        }

        return $this->storage->normalizeRelativePath($relative);
    }

    private function isExcludedRelativePath(string $relative, string $origin): bool
    {
        $relative = strtolower(str_replace('\\', '/', $relative));

        $prefixes = [
            'temp/',
            'temp/whatsapp-meta/',
            'temp_integracion_boleta/',
        ];

        foreach ($prefixes as $prefix) {
            if ($relative === rtrim($prefix, '/') || stripos($relative, $prefix) === 0) {
                return true;
            }
        }

        if ($origin === 'local' && preg_match('/^rotulado[^\/]*\.(pdf|zip)$/i', $relative)) {
            return true;
        }

        if (basename($relative) === '.gitignore') {
            return true;
        }

        return false;
    }

    private function normalizeSubdirFilter(string $subdir): ?string
    {
        $subdir = trim(str_replace('\\', '/', $subdir), '/');
        if ($subdir === '') {
            return null;
        }

        return $this->storage->normalizeRelativePath($subdir) ?? $subdir;
    }

    private function uploadDisk(): string
    {
        return (string) config('object_storage.upload_disk', 'local');
    }

    private function s3Prefix(): string
    {
        return trim(str_replace('\\', '/', (string) config('object_storage.s3_prefix', '')), '/');
    }
}
