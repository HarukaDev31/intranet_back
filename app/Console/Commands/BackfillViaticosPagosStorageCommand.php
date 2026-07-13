<?php

namespace App\Console\Commands;

use App\Contracts\ObjectStorageConnectorInterface;
use App\Models\ViaticoPago;
use App\Support\Storage\StoragePathSanitizer;
use App\Traits\UsesObjectStorage;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class BackfillViaticosPagosStorageCommand extends Command
{
    use UsesObjectStorage;

    protected $signature = 'viaticos:backfill-pagos-storage
        {--viatico-id= : Solo un viático (ej. 223)}
        {--all : Todos los pagos sin file_path válido}
        {--dry-run : Simular sin subir ni actualizar BD}
        {--force : Sobrescribir en S3 si ya existe la clave}
        {--window=600 : Ventana en segundos alrededor de created_at para emparejar archivos}';

    protected $description = 'Busca comprobantes de viaticos_pagos en disco/S3, sube faltantes y actualiza file_path en BD';

    /** @var ObjectStorageConnectorInterface */
    private $storage;

    /** @var array<string, bool> */
    private $referencedPaths = [];

    /** @var array<string, array{relative: string, absolute: ?string, source: string, ts: int}> */
    private $candidateFiles = [];

    /** @var int */
    private $updated = 0;

    /** @var int */
    private $uploaded = 0;

    /** @var int */
    private $alreadyOnS3 = 0;

    /** @var int */
    private $notFound = 0;

    /** @var int */
    private $ambiguous = 0;

    /** @var int */
    private $failed = 0;

    public function handle(ObjectStorageConnectorInterface $storage): int
    {
        $this->storage = $storage;

        $viaticoId = $this->option('viatico-id');
        $processAll = (bool) $this->option('all');

        if (!$processAll && ($viaticoId === null || $viaticoId === '')) {
            $this->error('Indica --viatico-id=ID o --all');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $window = max(60, (int) $this->option('window'));

        $this->referencedPaths = $this->loadReferencedPaths();
        $this->buildCandidateIndex();

        $query = ViaticoPago::query()
            ->with('viatico:id,created_at')
            ->where(function ($q) {
                $q->whereNull('file_path')
                    ->orWhere('file_path', '')
                    ->orWhere('file_path', 'storage')
                    ->orWhere('file_path', 'like', '%/storage');
            })
            ->orderBy('viatico_id')
            ->orderBy('id');

        if (!$processAll) {
            $query->where('viatico_id', (int) $viaticoId);
        }

        $pagos = $query->get();
        if ($pagos->isEmpty()) {
            $this->info('No hay pagos sin file_path válido.');

            return self::SUCCESS;
        }

        $this->info('Pagos a reparar: ' . $pagos->count());
        $this->line('Candidatos en disco/S3 (viaticos_pagos): ' . count($this->candidateFiles));

        if ($dryRun) {
            $this->warn('Modo dry-run: no se subirá ni actualizará BD.');
        }

        foreach ($pagos->groupBy('viatico_id') as $groupViaticoId => $groupPagos) {
            $this->processViaticoGroup((int) $groupViaticoId, $groupPagos, $window, $dryRun, $force);
        }

        $this->newLine();
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['BD actualizados', $this->updated],
                ['Subidos a S3', $this->uploaded],
                ['Ya existían en S3', $this->alreadyOnS3],
                ['Sin archivo encontrado', $this->notFound],
                ['Emparejamiento ambiguo', $this->ambiguous],
                ['Errores', $this->failed],
            ]
        );

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param Collection<int, ViaticoPago> $pagos
     */
    private function processViaticoGroup(int $viaticoId, Collection $pagos, int $window, bool $dryRun, bool $force): void
    {
        $anchor = $pagos->min(function (ViaticoPago $pago) {
            return $pago->created_at ?? optional($pago->viatico)->created_at ?? now();
        });

        if (!$anchor instanceof Carbon) {
            $anchor = Carbon::parse((string) $anchor);
        }

        $fromTs = $anchor->copy()->subSeconds($window)->getTimestamp();
        $toTs = $anchor->copy()->addSeconds($window)->getTimestamp();

        $candidates = collect($this->candidateFiles)
            ->filter(function (array $file) use ($fromTs, $toTs) {
                return $file['ts'] >= $fromTs && $file['ts'] <= $toTs;
            })
            ->sortBy('ts')
            ->values();

        $sortedPagos = $pagos->sortBy('id')->values();

        if ($candidates->count() < $sortedPagos->count()) {
            $this->warn("Viático #{$viaticoId}: {$sortedPagos->count()} pagos sin archivo, {$candidates->count()} candidatos en ventana.");
        }

        if ($candidates->count() > $sortedPagos->count()) {
            $this->ambiguous += $sortedPagos->count();
            $this->line("Viático #{$viaticoId}: demasiados candidatos ({$candidates->count()}) para {$sortedPagos->count()} pagos; omitido por ambigüedad.");

            return;
        }

        if ($candidates->isEmpty()) {
            $this->notFound += $sortedPagos->count();

            return;
        }

        foreach ($sortedPagos as $index => $pago) {
            $candidate = $candidates->get($index);
            if ($candidate === null) {
                $this->notFound++;
                $this->line("  pago #{$pago->id} ({$pago->concepto}): sin candidato");

                continue;
            }

            $this->applyCandidateToPago($pago, $candidate, $dryRun, $force);
        }
    }

    /**
     * @param array{relative: string, absolute: ?string, source: string, ts: int} $candidate
     */
    private function applyCandidateToPago(ViaticoPago $pago, array $candidate, bool $dryRun, bool $force): void
    {
        $relative = StoragePathSanitizer::relativePath($candidate['relative']);
        $uploadPath = $this->storageUploadPathFromDb($relative) ?? $relative;

        try {
            $onS3 = $this->pathExistsOnS3($uploadPath) || $this->pathExistsOnS3($relative);

            if (!$onS3) {
                if ($candidate['absolute'] === null || !is_file($candidate['absolute'])) {
                    $this->notFound++;
                    $this->line("  pago #{$pago->id}: candidato {$relative} no está en S3 ni en disco local");

                    return;
                }

                if ($dryRun) {
                    $this->uploaded++;
                    $this->updated++;
                    $this->line("[dry-run] pago #{$pago->id} ← {$candidate['absolute']} → s3://{$uploadPath}");

                    return;
                }

                if (!$force && $this->pathExistsOnS3($uploadPath)) {
                    $this->alreadyOnS3++;
                    $relative = $this->storageCdnDbPath($relative);
                } else {
                    $contents = (string) file_get_contents($candidate['absolute']);
                    $stored = $this->storagePutContents($uploadPath, $contents);
                    $relative = $this->storageCdnDbPath($stored);
                    $this->uploaded++;
                }
            } else {
                $relative = $this->storageCdnDbPath($relative);
                $this->alreadyOnS3++;

                if ($dryRun) {
                    $this->updated++;
                    $this->line("[dry-run] pago #{$pago->id} ← ya en S3: {$relative}");

                    return;
                }
            }

            if (!$dryRun) {
                $meta = $this->buildFileMetadata($candidate['absolute'], $relative);
                ViaticoPago::where('id', $pago->id)->update(array_merge(['file_path' => $relative], $meta));
                $this->referencedPaths[strtolower($relative)] = true;
                $this->updated++;
                $this->line("  pago #{$pago->id} → {$relative}");
            }
        } catch (\Throwable $e) {
            $this->failed++;
            $this->error("  pago #{$pago->id}: " . $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFileMetadata(?string $absolutePath, string $relativePath): array
    {
        if ($absolutePath === null || !is_file($absolutePath)) {
            $ext = pathinfo($relativePath, PATHINFO_EXTENSION);

            return array_filter([
                'file_extension' => $ext !== '' ? $ext : null,
            ]);
        }

        $ext = pathinfo($absolutePath, PATHINFO_EXTENSION);
        $mime = @mime_content_type($absolutePath) ?: null;

        return [
            'file_size' => filesize($absolutePath) ?: null,
            'file_original_name' => basename($absolutePath),
            'file_mime_type' => $mime,
            'file_extension' => $ext !== '' ? $ext : null,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function loadReferencedPaths(): array
    {
        $paths = [];

        $collect = function ($path) use (&$paths) {
            $normalized = $this->storage->normalizeRelativePath((string) $path);
            if ($normalized !== null && $normalized !== '') {
                $paths[strtolower($normalized)] = true;
            }
        };

        foreach (DB::table('viaticos_pagos')->whereNotNull('file_path')->where('file_path', '!=', '')->pluck('file_path') as $path) {
            $collect($path);
        }

        foreach (DB::table('viaticos_retribuciones')->whereNotNull('file_path')->where('file_path', '!=', '')->pluck('file_path') as $path) {
            $collect($path);
        }

        foreach (DB::table('viaticos')->whereNotNull('receipt_file')->where('receipt_file', '!=', '')->pluck('receipt_file') as $path) {
            $collect($path);
        }

        foreach (DB::table('viaticos')->whereNotNull('payment_receipt_file')->where('payment_receipt_file', '!=', '')->pluck('payment_receipt_file') as $path) {
            $collect($path);
        }

        return $paths;
    }

    private function buildCandidateIndex(): void
    {
        foreach ([
            storage_path('app/public/viaticos_pagos'),
            storage_path('app/viaticos_pagos'),
            storage_path('app/public/viaticos'),
            storage_path('app/viaticos'),
        ] as $root) {
            $this->indexLocalDirectory($root, 'local');
        }

        if ($this->uploadDisk() === 's3') {
            $this->indexS3Prefix('viaticos_pagos');
        }
    }

    private function indexLocalDirectory(string $root, string $source): void
    {
        if (!is_dir($root)) {
            return;
        }

        $prefix = trim(str_replace('\\', '/', $root), '/');
        $base = trim(str_replace('\\', '/', storage_path('app')), '/');
        $isPublic = stripos($prefix, '/public/') !== false || str_ends_with($prefix, '/public');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $absolute = str_replace('\\', '/', $fileInfo->getPathname());
            $relative = ltrim(substr($absolute, strlen($base) + 1), '/');
            if ($isPublic && stripos($relative, 'public/') === 0) {
                $relative = substr($relative, 7);
            }

            $this->registerCandidate($relative, $absolute, $source, $fileInfo);
        }
    }

    private function indexS3Prefix(string $prefix): void
    {
        try {
            $files = Storage::disk('s3')->allFiles($prefix);
        } catch (\Throwable $e) {
            $this->warn('No se pudo listar S3/' . $prefix . ': ' . $e->getMessage());

            return;
        }

        foreach ($files as $relative) {
            $relative = StoragePathSanitizer::relativePath($relative);
            $this->registerCandidate($relative, null, 's3', null, $this->timestampFromFilename($relative));
        }
    }

    private function registerCandidate(string $relative, ?string $absolute, string $source, ?SplFileInfo $fileInfo = null, ?int $ts = null): void
    {
        $relative = StoragePathSanitizer::relativePath($relative);
        if ($relative === '' || !preg_match('/\.(jpe?g|png|gif|pdf|docx?|xlsx?)$/i', $relative)) {
            return;
        }

        $key = strtolower($relative);
        if (isset($this->referencedPaths[$key])) {
            return;
        }

        if (isset($this->candidateFiles[$key])) {
            return;
        }

        if ($ts === null) {
            $ts = $this->timestampFromFilename($relative);
            if ($ts === null && $fileInfo !== null) {
                $ts = $fileInfo->getMTime();
            }
        }

        if ($ts === null) {
            return;
        }

        $this->candidateFiles[$key] = [
            'relative' => $relative,
            'absolute' => $absolute,
            'source' => $source,
            'ts' => $ts,
        ];
    }

    private function timestampFromFilename(string $relative): ?int
    {
        $basename = basename($relative);
        if (preg_match('/^(\d{9,11})_/u', $basename, $matches)) {
            $ts = (int) $matches[1];
            if ($ts > 1_500_000_000 && $ts < 2_200_000_000) {
                return $ts;
            }
        }

        return null;
    }

    private function uploadDisk(): string
    {
        return (string) config('object_storage.upload_disk', 'local');
    }

    private function pathExistsOnS3(string $path): bool
    {
        if (!method_exists($this->storage, 'existsOnS3')) {
            return $this->storage->exists($path);
        }

        return (bool) call_user_func([$this->storage, 'existsOnS3'], $path);
    }
}
