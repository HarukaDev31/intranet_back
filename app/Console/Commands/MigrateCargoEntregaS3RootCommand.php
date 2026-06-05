<?php

namespace App\Console\Commands;

use App\Contracts\ObjectStorageConnectorInterface;
use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MigrateCargoEntregaS3RootCommand extends Command
{
    protected $signature = 'storage:migrate-cargo-entrega-s3-root
        {--dry-run : Simular sin copiar}
        {--delete-source : Borrar objeto origen tras copiar (mover)}
        {--prefix=probusiness : Prefijo legacy en el bucket (ej. probusiness)}
        {--force : Sobrescribir si ya existe en la raíz del bucket}
        {--scan-s3 : Incluir PDFs en S3 bajo el prefijo legacy aunque no estén en BD}
        {--limit=0 : Máximo de objetos a procesar (0 = sin límite)}';

    protected $description = 'Mueve PDFs de cargo de entrega de {prefix}/entregas/... a entregas/... en la raíz del bucket S3';

    /** @var ObjectStorageConnectorInterface */
    private $storage;

    /** @var S3Client|null */
    private $s3;

    /** @var string */
    private $bucket = '';

    /** @var array<string, bool> */
    private $seenRelative = [];

    private int $moved = 0;

    private int $skippedAlreadyRoot = 0;

    private int $skippedMissing = 0;

    private int $skippedExists = 0;

    private int $failed = 0;

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

        $legacyPrefix = trim((string) $this->option('prefix'), '/');
        $dryRun = (bool) $this->option('dry-run');
        $deleteSource = (bool) $this->option('delete-source');
        $force = (bool) $this->option('force');
        $limit = max(0, (int) $this->option('limit'));

        $this->info('Migración cargo entrega → raíz S3');
        $this->line('Bucket: ' . $this->bucket);
        $this->line('Prefijo origen: ' . ($legacyPrefix !== '' ? $legacyPrefix . '/' : '(ninguno)'));
        $this->line('Destino: entregas/cargo_entrega/... (raíz del bucket)');
        if ($dryRun) {
            $this->warn('Modo dry-run: no se modificará S3.');
        }

        $relativePaths = $this->collectRelativePaths($legacyPrefix);

        if ($relativePaths === []) {
            $this->warn('No se encontraron rutas candidatas.');

            return self::SUCCESS;
        }

        $this->info('Objetos candidatos: ' . count($relativePaths));

        $processed = 0;
        foreach ($relativePaths as $relative) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $this->migrateOne($relative, $legacyPrefix, $dryRun, $deleteSource, $force);
            $processed++;
        }

        $this->newLine();
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Copiados / movidos a raíz', $this->moved],
                ['Ya en raíz (omitidos)', $this->skippedAlreadyRoot],
                ['Destino existía (omitidos)', $this->skippedExists],
                ['Origen no encontrado', $this->skippedMissing],
                ['Errores', $this->failed],
            ]
        );

        if (!$dryRun && $this->moved > 0) {
            $this->line('Recuerda dejar AWS_UPLOAD_PREFIX vacío en prod para nuevas subidas.');
        }

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function collectRelativePaths(string $legacyPrefix): array
    {
        $paths = [];

        $rows = DB::table('contenedor_consolidado_cotizacion')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNotNull('cargo_entrega_pdf_url')
                    ->where('cargo_entrega_pdf_url', '!=', '')
                    ->orWhere(function ($q2) {
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

        if ((bool) $this->option('scan-s3') && $legacyPrefix !== '') {
            $scanPrefix = $legacyPrefix . '/entregas/cargo_entrega/';
            $this->line('Escaneando S3: ' . $scanPrefix);

            try {
                $paginator = $this->s3->getPaginator('ListObjectsV2', [
                    'Bucket' => $this->bucket,
                    'Prefix' => $scanPrefix,
                ]);

                foreach ($paginator as $page) {
                    foreach ($page['Contents'] ?? [] as $object) {
                        $key = (string) ($object['Key'] ?? '');
                        if ($key === '' || substr($key, -1) === '/') {
                            continue;
                        }

                        $relative = $this->stripLegacyPrefixFromKey($key, $legacyPrefix);
                        if ($relative !== null) {
                            $paths[] = $relative;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->warn('No se pudo listar S3: ' . $e->getMessage());
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

    private function migrateOne(
        string $relative,
        string $legacyPrefix,
        bool $dryRun,
        bool $deleteSource,
        bool $force
    ): void {
        $destKey = ltrim($relative, '/');

        if (strpos($destKey, 'entregas/cargo_entrega/') !== 0) {
            $this->line("  omitido (ruta no es cargo entrega): {$destKey}");

            return;
        }

        $sourceKey = $this->resolveSourceKey($destKey, $legacyPrefix);

        if ($sourceKey === null) {
            if ($this->objectExists($destKey)) {
                $this->skippedAlreadyRoot++;

                return;
            }

            $this->warn("  origen no encontrado: {$destKey}");
            $this->skippedMissing++;

            return;
        }

        if ($sourceKey === $destKey) {
            $this->skippedAlreadyRoot++;

            return;
        }

        if ($this->objectExists($destKey) && !$force) {
            $this->line("  destino ya existe: {$destKey}");
            $this->skippedExists++;

            return;
        }

        $action = $deleteSource ? 'mover' : 'copiar';
        $this->line("  {$action}: {$sourceKey} → {$destKey}");

        if ($dryRun) {
            $this->moved++;

            return;
        }

        try {
            $this->s3->copyObject([
                'Bucket' => $this->bucket,
                'CopySource' => $this->bucket . '/' . str_replace('%2F', '/', rawurlencode($sourceKey)),
                'Key' => $destKey,
            ]);

            if ($deleteSource) {
                $this->s3->deleteObject([
                    'Bucket' => $this->bucket,
                    'Key' => $sourceKey,
                ]);
            }

            $this->moved++;
        } catch (\Throwable $e) {
            $this->error("  error en {$destKey}: " . $e->getMessage());
            $this->failed++;
        }
    }

    private function resolveSourceKey(string $destKey, string $legacyPrefix): ?string
    {
        if ($this->objectExists($destKey)) {
            return $destKey;
        }

        foreach ($this->sourceKeyCandidates($destKey, $legacyPrefix) as $candidate) {
            if ($candidate !== $destKey && $this->objectExists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function sourceKeyCandidates(string $relativePath, string $legacyPrefix): array
    {
        $relativePath = ltrim($relativePath, '/');
        $candidates = [];

        if ($legacyPrefix !== '') {
            $candidates[] = $legacyPrefix . '/' . $relativePath;
        }

        $envPrefix = trim((string) config('object_storage.s3_prefix', ''), '/');
        if ($envPrefix !== '') {
            $candidates[] = $envPrefix . '/' . $relativePath;
        }

        $diskRoot = trim(str_replace('\\', '/', (string) config('filesystems.disks.s3.root', '')), '/');
        if ($diskRoot !== '') {
            $candidates[] = $diskRoot . '/' . $relativePath;
        }

        $candidates[] = $relativePath;

        return array_values(array_unique($candidates));
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

    private function stripLegacyPrefixFromKey(string $key, string $legacyPrefix): ?string
    {
        $key = ltrim(str_replace('\\', '/', $key), '/');
        $prefix = trim($legacyPrefix, '/') . '/';

        if ($prefix !== '/' && stripos($key, $prefix) === 0) {
            $relative = substr($key, strlen($prefix));

            return strpos($relative, 'entregas/cargo_entrega/') === 0 ? $relative : null;
        }

        return strpos($key, 'entregas/cargo_entrega/') === 0 ? $key : null;
    }

    private function objectExists(string $key): bool
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
