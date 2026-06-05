<?php

namespace App\Console\Commands;

use App\Contracts\ObjectStorageConnectorInterface;
use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class MigrateCargoEntregaS3RootCommand extends Command
{
    protected $signature = 'storage:migrate-cargo-entrega-to-s3
        {--dry-run : Simular sin subir}
        {--delete-source : Borrar archivo local tras subir a S3}
        {--force : Sobrescribir si ya existe en S3}
        {--scan-local : Incluir PDFs en disco bajo entregas/cargo_entrega/ aunque no estén en BD}
        {--limit=0 : Máximo de archivos a procesar (0 = sin límite)}';

    protected $description = 'Sube PDFs de cargo de entrega desde disco local a entregas/cargo_entrega/... en la raíz del bucket S3';

    /** @var ObjectStorageConnectorInterface */
    private $storage;

    /** @var S3Client|null */
    private $s3;

    /** @var string */
    private $bucket = '';

    /** @var array<string, bool> */
    private $seenRelative = [];

    /** @var int */
    private $uploaded = 0;

    /** @var int */
    private $skippedAlreadyS3 = 0;

    /** @var int */
    private $skippedMissingLocal = 0;

    /** @var int */
    private $failed = 0;

    public function handle(ObjectStorageConnectorInterface $storage): int
    {
        $this->storage = $storage;

        if ((string) config('filesystems.disks.s3.bucket') === '') {
            $this->error('AWS_BUCKET no está configurado.');

            return self::FAILURE;
        }

        $this->bucket = (string) config('filesystems.disks.s3.bucket');
        $this->s3 = $this->resolveS3Client();

        if ($this->s3 === null) {
            $this->error('No se pudo obtener cliente S3.');

            return self::FAILURE;
        }

        try {
            $this->s3->headBucket(['Bucket' => $this->bucket]);
        } catch (\Throwable $e) {
            $this->error('No se pudo conectar al bucket: ' . $e->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $deleteSource = (bool) $this->option('delete-source');
        $force = (bool) $this->option('force');
        $limit = max(0, (int) $this->option('limit'));

        $s3Prefix = trim((string) config('object_storage.s3_prefix', ''), '/');

        $this->info('Migración local → S3 (cargo de entrega)');
        $this->line('Bucket: ' . $this->bucket);
        $this->line('Clave S3 destino: entregas/cargo_entrega/... (raíz del bucket)');
        $this->line('Orígenes locales: storage/app, storage/app/public, public/');
        if ($s3Prefix !== '') {
            $this->warn('AWS_UPLOAD_PREFIX=' . $s3Prefix . ' — este comando sube a la raíz del bucket; deja el prefijo vacío para nuevas subidas.');
        }
        if ($dryRun) {
            $this->warn('Modo dry-run: no se modificará S3 ni archivos locales.');
        }

        $relativePaths = $this->collectRelativePaths();

        if ($relativePaths === []) {
            $this->warn('No se encontraron rutas candidatas.');

            return self::SUCCESS;
        }

        $this->info('Archivos candidatos: ' . count($relativePaths));

        $processed = 0;
        foreach ($relativePaths as $relative) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $this->uploadOne($relative, $dryRun, $deleteSource, $force);
            $processed++;
        }

        $this->newLine();
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Subidos a S3', $this->uploaded],
                ['Ya en S3 (omitidos)', $this->skippedAlreadyS3],
                ['No encontrados en local', $this->skippedMissingLocal],
                ['Errores', $this->failed],
            ]
        );

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function collectRelativePaths(): array
    {
        $paths = [];

        $rows = DB::table('contenedor_consolidado_cotizacion')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where(function ($q1) {
                    $q1->whereNotNull('cargo_entrega_pdf_url')
                        ->where('cargo_entrega_pdf_url', '!=', '');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('cargo_entrega_pdf_firmado_url')
                        ->where('cargo_entrega_pdf_firmado_url', '!=', '');
                });
            })
            ->get(['cargo_entrega_pdf_url', 'cargo_entrega_pdf_firmado_url']);

        foreach ($rows as $row) {
            foreach (['cargo_entrega_pdf_url', 'cargo_entrega_pdf_firmado_url'] as $col) {
                $normalized = $this->normalizeCargoPath((string) ($row->{$col} ?? ''));
                if ($normalized !== null) {
                    $paths[] = $normalized;
                }
            }
        }

        if ((bool) $this->option('scan-local')) {
            foreach ($this->localScanRoots() as $root) {
                if (!is_dir($root)) {
                    continue;
                }

                $this->line('Escaneando local: ' . $root);

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
                );

                /** @var SplFileInfo $fileInfo */
                foreach ($iterator as $fileInfo) {
                    if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'pdf') {
                        continue;
                    }

                    $absolute = str_replace('\\', '/', $fileInfo->getPathname());
                    $rootNorm = rtrim(str_replace('\\', '/', $root), '/');
                    if (stripos($absolute, $rootNorm . '/') !== 0) {
                        continue;
                    }

                    $relative = ltrim(substr($absolute, strlen($rootNorm)), '/');
                    $normalized = $this->normalizeCargoPath($relative);
                    if ($normalized !== null) {
                        $paths[] = $normalized;
                    }
                }
            }
        }

        $unique = [];
        foreach ($paths as $path) {
            if (!isset($this->seenRelative[$path])) {
                $this->seenRelative[$path] = true;
                $unique[] = $path;
            }
        }

        sort($unique);

        return $unique;
    }

    /**
     * @return string[]
     */
    private function localScanRoots(): array
    {
        return [
            storage_path('app/public/entregas/cargo_entrega'),
            storage_path('app/entregas/cargo_entrega'),
            public_path('entregas/cargo_entrega'),
            public_path('storage/entregas/cargo_entrega'),
        ];
    }

    private function uploadOne(string $relative, bool $dryRun, bool $deleteSource, bool $force): void
    {
        $destKey = ltrim($relative, '/');

        if (strpos($destKey, 'entregas/cargo_entrega/') !== 0) {
            return;
        }

        if ($this->objectExistsAtBucketRoot($destKey) && !$force) {
            $this->line("  ya en S3: {$destKey}");
            $this->skippedAlreadyS3++;

            return;
        }

        $localPath = $this->resolveLocalPath($destKey);
        if ($localPath === null) {
            $this->warn("  no en local: {$destKey}");
            $this->skippedMissingLocal++;

            return;
        }

        $this->line('  subir: ' . $localPath . ' → s3://' . $this->bucket . '/' . $destKey);

        if ($dryRun) {
            $this->uploaded++;

            return;
        }

        try {
            $stream = fopen($localPath, 'rb');
            if ($stream === false) {
                throw new \RuntimeException('No se pudo abrir: ' . $localPath);
            }

            $this->s3->putObject([
                'Bucket' => $this->bucket,
                'Key' => $destKey,
                'Body' => $stream,
                'ContentType' => 'application/pdf',
            ]);

            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($deleteSource && !@unlink($localPath)) {
                $this->warn("  subido pero no se pudo borrar local: {$localPath}");
            }

            $this->uploaded++;
        } catch (\Throwable $e) {
            $this->error("  error en {$destKey}: " . $e->getMessage());
            $this->failed++;
        }
    }

    private function resolveLocalPath(string $relative): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        $candidates = [
            storage_path('app/public/' . $relative),
            storage_path('app/' . $relative),
            public_path('storage/' . $relative),
            public_path($relative),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function normalizeCargoPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $normalized = $this->storage->normalizeRelativePath($path);
        if ($normalized === null || $normalized === '') {
            return null;
        }

        if (strpos($normalized, 'entregas/cargo_entrega/') !== 0) {
            return null;
        }

        return $normalized;
    }

    /** Existe en la raíz del bucket (sin AWS_UPLOAD_PREFIX del disco Laravel). */
    private function objectExistsAtBucketRoot(string $key): bool
    {
        try {
            return $this->s3->doesObjectExist($this->bucket, ltrim($key, '/'));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveS3Client(): ?S3Client
    {
        try {
            $adapter = Storage::disk('s3')->getAdapter();
            if (method_exists($adapter, 'getClient')) {
                /** @var S3Client $client */
                $client = $adapter->getClient();

                return $client;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}
