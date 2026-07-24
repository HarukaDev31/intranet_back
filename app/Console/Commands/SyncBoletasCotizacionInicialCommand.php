<?php

namespace App\Console\Commands;

use App\Contracts\ObjectStorageConnectorInterface;
use App\Support\Storage\StoragePathSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SyncBoletasCotizacionInicialCommand extends Command
{
    protected $signature = 'storage:sync-boletas-cotizacion-inicial
                            {path? : URL CDN, ruta relativa (boletas/...) o basename del PDF/Excel}
                            {--all : Auditar todas las rutas en calculadora_importacion}
                            {--upload : Subir a S3 los PENDING_LOCAL (existen en EC2, faltan en CDN/S3)}
                            {--dry-run : Simular sin subir}
                            {--details : Listar cada archivo con su estado}
                            {--check-cdn : Verificar HTTP HEAD contra OBJECT_STORAGE_CDN_URL}
                            {--column=pdf : Columnas: pdf (url_cotizacion_pdf), excel (url_cotizacion), both}
                            {--limit=0 : Máximo de filas BD a procesar (0 = sin límite)}
                            {--export-missing= : Exportar MISSING a CSV}';

    protected $description = 'Valida boletas/cotización inicial en BD vs S3/CDN/disco local y sube las que solo estén en el servidor EC2';

    /** @var ObjectStorageConnectorInterface */
    private $storage;

    /** @var array<string, int> */
    private $stats = [
        'total' => 0,
        'ok_s3' => 0,
        'ok_cdn' => 0,
        'pending_local' => 0,
        'uploaded' => 0,
        'missing' => 0,
        'failed' => 0,
        'skipped' => 0,
    ];

    /** @var array<int, array<string, mixed>> */
    private $missingRows = [];

    /** @var array<string, bool> */
    private $seenPaths = [];

    public function handle(ObjectStorageConnectorInterface $storage): int
    {
        $this->storage = $storage;

        $pathArg = trim((string) ($this->argument('path') ?? ''));
        $all = (bool) $this->option('all');
        $upload = (bool) $this->option('upload');
        $dryRun = (bool) $this->option('dry-run');
        $details = (bool) $this->option('details');
        $checkCdn = (bool) $this->option('check-cdn');
        $column = strtolower(trim((string) $this->option('column')));
        $limit = max(0, (int) $this->option('limit'));

        if (!in_array($column, ['pdf', 'excel', 'both'], true)) {
            $this->error('--column debe ser: pdf, excel o both');

            return self::FAILURE;
        }

        if ($pathArg === '' && !$all) {
            $this->error('Indica {path} (URL/ruta/basename) o usa --all.');

            return self::FAILURE;
        }

        if ($upload && $this->uploadDisk() !== 's3') {
            $this->error('FILESYSTEM_UPLOAD_DISK debe ser "s3" para --upload. Actual: ' . $this->uploadDisk());

            return self::FAILURE;
        }

        $this->info('Sync boletas cotización inicial');
        $this->line('Bucket: ' . (config('filesystems.disks.s3.bucket') ?: '(no configurado)'));
        $this->line('CDN: ' . ($this->cdnBase() !== '' ? $this->cdnBase() : '(no configurado)'));
        if ($dryRun) {
            $this->warn('Modo dry-run: no se subirá nada.');
        }

        $rows = $pathArg !== ''
            ? $this->rowsForPath($pathArg, $column)
            : $this->rowsFromDatabase($column, $limit);

        if ($rows === []) {
            if ($pathArg !== '') {
                // Path suelto (no en BD): auditar/subir igual por disco/S3.
                $relative = $this->normalizeIncomingPath($pathArg);
                if ($relative === null) {
                    $this->error('No se pudo interpretar la ruta: ' . $pathArg);

                    return self::FAILURE;
                }

                $rows = [[
                    'id' => null,
                    'cliente' => null,
                    'column' => 'path',
                    'db_path' => $relative,
                ]];
                $this->comment('Ruta no encontrada en BD; se valida igual contra disco/S3/CDN.');
            } else {
                $this->info('No hay registros para procesar.');

                return self::SUCCESS;
            }
        }

        $this->info('Candidatos: ' . count($rows));

        foreach ($rows as $row) {
            $this->processRow($row, $upload, $dryRun, $details, $checkCdn);
        }

        $this->newLine();
        $this->table(
            ['Estado', 'Cantidad', 'Descripción'],
            [
                ['Total', $this->stats['total'], 'Rutas evaluadas'],
                ['OK en S3', $this->stats['ok_s3'], 'Archivo presente en el bucket'],
                ['OK CDN', $this->stats['ok_cdn'], 'HTTP HEAD 2xx/3xx en CDN (--check-cdn)'],
                ['Pendiente local', $this->stats['pending_local'], 'En EC2 (storage) pero no en S3'],
                ['Subidos', $this->stats['uploaded'], 'Copiados local → S3' . ($dryRun ? ' (dry-run)' : '')],
                ['Sin archivo', $this->stats['missing'], 'Ni en S3 ni en disco local'],
                ['Fallidos', $this->stats['failed'], 'Error al subir'],
                ['Omitidos', $this->stats['skipped'], 'Duplicados / sin ruta'],
            ]
        );

        $exportPath = (string) $this->option('export-missing');
        if ($exportPath !== '' && $this->missingRows !== []) {
            $this->exportMissingCsv($exportPath);
        }

        if ($this->stats['pending_local'] > 0 && !$upload) {
            $this->newLine();
            $this->comment('Hay archivos solo en EC2. Súbelos con:');
            $this->line('  php artisan storage:sync-boletas-cotizacion-inicial --all --upload --column=both');
            if ($pathArg !== '') {
                $this->line('  php artisan storage:sync-boletas-cotizacion-inicial "' . $pathArg . '" --upload');
            }
        }

        if ($this->stats['missing'] > 0) {
            $this->newLine();
            $this->warn('MISSING: la ruta está en BD (o pedida) pero el archivo no está en S3 ni en storage local del servidor.');
        }

        return $this->stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array{id: int|null, cliente: string|null, column: string, db_path: string}>
     */
    private function rowsFromDatabase(string $column, int $limit): array
    {
        $select = ['id', 'nombre_cliente'];
        $columns = $this->columnNames($column);
        foreach ($columns as $col) {
            $select[] = $col;
        }

        $query = DB::table('calculadora_importacion')->select($select)->orderBy('id');

        $query->where(function ($q) use ($columns) {
            foreach ($columns as $i => $col) {
                $method = $i === 0 ? 'where' : 'orWhere';
                $q->{$method}(function ($inner) use ($col) {
                    $inner->whereNotNull($col)->where($col, '!=', '');
                });
            }
        });

        $rows = [];
        $processed = 0;

        $query->chunk(200, function ($chunk) use (&$rows, &$processed, $limit, $columns) {
            foreach ($chunk as $item) {
                if ($limit > 0 && $processed >= $limit) {
                    return false;
                }

                foreach ($columns as $col) {
                    $raw = trim((string) ($item->{$col} ?? ''));
                    if ($raw === '') {
                        continue;
                    }

                    $relative = $this->storage->normalizeRelativePath($raw);
                    if ($relative === null || $relative === '') {
                        continue;
                    }

                    // Solo boletas / cotización inicial (evita otros paths en url_cotizacion).
                    if (!$this->isCotizacionInicialPath($relative)) {
                        continue;
                    }

                    $rows[] = [
                        'id' => (int) $item->id,
                        'cliente' => (string) ($item->nombre_cliente ?? ''),
                        'column' => $col,
                        'db_path' => $relative,
                    ];
                    $processed++;

                    if ($limit > 0 && $processed >= $limit) {
                        return false;
                    }
                }
            }
        });

        return $rows;
    }

    /**
     * @return array<int, array{id: int|null, cliente: string|null, column: string, db_path: string}>
     */
    private function rowsForPath(string $pathArg, string $column): array
    {
        $relative = $this->normalizeIncomingPath($pathArg);
        $basename = strtolower(basename($relative ?? $pathArg));
        $columns = $this->columnNames($column);
        $rows = [];

        $query = DB::table('calculadora_importacion')
            ->select(array_merge(['id', 'nombre_cliente'], $columns))
            ->orderBy('id');

        $query->where(function ($q) use ($columns, $pathArg, $relative, $basename) {
            foreach ($columns as $col) {
                $q->orWhere($col, 'like', '%' . basename($pathArg) . '%');
                if ($relative !== null) {
                    $q->orWhere($col, $relative)
                        ->orWhere($col, 'like', '%/' . basename($relative));
                }
                if ($basename !== '') {
                    $q->orWhere($col, 'like', '%' . $basename);
                }
            }
        });

        foreach ($query->get() as $item) {
            foreach ($columns as $col) {
                $raw = trim((string) ($item->{$col} ?? ''));
                if ($raw === '') {
                    continue;
                }
                $dbRelative = $this->storage->normalizeRelativePath($raw);
                if ($dbRelative === null) {
                    continue;
                }

                $match = $relative !== null && (
                    strcasecmp($dbRelative, $relative) === 0
                    || strcasecmp(basename($dbRelative), basename($relative)) === 0
                );
                $match = $match || strcasecmp(basename($dbRelative), $basename) === 0;

                if (!$match) {
                    continue;
                }

                $rows[] = [
                    'id' => (int) $item->id,
                    'cliente' => (string) ($item->nombre_cliente ?? ''),
                    'column' => $col,
                    'db_path' => $dbRelative,
                ];
            }
        }

        if ($rows === [] && $relative !== null) {
            $rows[] = [
                'id' => null,
                'cliente' => null,
                'column' => 'path',
                'db_path' => $relative,
            ];
        }

        return $rows;
    }

    /**
     * @param array{id: int|null, cliente: string|null, column: string, db_path: string} $row
     */
    private function processRow(array $row, bool $upload, bool $dryRun, bool $details, bool $checkCdn): void
    {
        $dbPath = $row['db_path'];
        $key = strtolower($dbPath);
        if (isset($this->seenPaths[$key])) {
            $this->stats['skipped']++;

            return;
        }
        $this->seenPaths[$key] = true;
        $this->stats['total']++;

        $sanitized = StoragePathSanitizer::relativePath($dbPath);
        $onS3 = $this->storage->existsOnS3($dbPath)
            || ($sanitized !== $dbPath && $this->storage->existsOnS3($sanitized));

        $localPath = $this->findLocalFile($dbPath, $sanitized);
        $hasLocal = $localPath !== null;

        $cdnOk = null;
        if ($checkCdn) {
            $cdnOk = $this->cdnHeadOk($sanitized !== '' ? $sanitized : $dbPath);
            if ($cdnOk) {
                $this->stats['ok_cdn']++;
            }
        }

        $status = 'MISSING';
        if ($onS3) {
            $status = 'OK_S3';
            $this->stats['ok_s3']++;
        } elseif ($hasLocal) {
            $status = 'PENDING_LOCAL';
            $this->stats['pending_local']++;

            if ($upload) {
                $s3Key = $sanitized !== '' ? $sanitized : $dbPath;
                if ($this->uploadLocalToS3($localPath, $s3Key, $dryRun)) {
                    $status = $dryRun ? 'WOULD_UPLOAD' : 'UPLOADED';
                    $this->stats['uploaded']++;
                    $onS3 = !$dryRun;
                } else {
                    $status = 'UPLOAD_FAILED';
                    $this->stats['failed']++;
                }
            }
        } else {
            $this->stats['missing']++;
            $this->missingRows[] = [
                'id' => $row['id'],
                'cliente' => $row['cliente'],
                'column' => $row['column'],
                'db_path' => $dbPath,
                'cdn_url' => $this->cdnUrlFor($dbPath),
            ];
        }

        if (!$details && !in_array($status, ['PENDING_LOCAL', 'MISSING', 'UPLOADED', 'WOULD_UPLOAD', 'UPLOAD_FAILED'], true)) {
            return;
        }

        $line = sprintf(
            '%s | id=%s | %s | local=%s | s3=%s | cdn=%s | %s',
            $status,
            $row['id'] !== null ? (string) $row['id'] : '-',
            $row['column'],
            $hasLocal ? 'SI' : 'NO',
            $onS3 ? 'SI' : 'NO',
            $cdnOk === null ? '-' : ($cdnOk ? 'SI' : 'NO'),
            $dbPath
        );

        if ($hasLocal) {
            $line .= ' | disk=' . $localPath;
        }

        if ($status === 'MISSING' || $status === 'UPLOAD_FAILED') {
            $this->warn($line);
        } elseif ($status === 'PENDING_LOCAL' || $status === 'WOULD_UPLOAD' || $status === 'UPLOADED') {
            $this->info($line);
        } else {
            $this->line($line);
        }
    }

    private function uploadLocalToS3(string $absoluteLocal, string $relativeS3, bool $dryRun): bool
    {
        try {
            if ($dryRun) {
                $this->line("  [dry-run] subiría → s3://{$relativeS3}");

                return true;
            }

            $stream = fopen($absoluteLocal, 'rb');
            if ($stream === false) {
                throw new \RuntimeException('No se pudo abrir: ' . $absoluteLocal);
            }

            $ok = Storage::disk('s3')->writeStream($relativeS3, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (!$ok) {
                throw new \RuntimeException('writeStream devolvió false');
            }

            if (!$this->storage->existsOnS3($relativeS3)) {
                throw new \RuntimeException('Subida OK pero existsOnS3=false para ' . $relativeS3);
            }

            $this->line("  ↑ subido → {$relativeS3}");

            return true;
        } catch (\Throwable $e) {
            $this->error('  Fallo subida ' . $relativeS3 . ': ' . $e->getMessage());

            return false;
        }
    }

    private function findLocalFile(string $dbPath, string $sanitized): ?string
    {
        $candidates = [
            storage_path('app/public/' . $dbPath),
            storage_path('app/' . $dbPath),
            storage_path('app/public/' . $sanitized),
            storage_path('app/' . $sanitized),
        ];

        $base = basename($dbPath);
        $baseSan = basename($sanitized);
        foreach ([$base, $baseSan] as $name) {
            if ($name === '') {
                continue;
            }
            $candidates[] = storage_path('app/public/boletas/' . $name);
            $candidates[] = storage_path('app/boletas/' . $name);
            $candidates[] = storage_path('app/public/templates/' . $name);
            $candidates[] = storage_path('app/templates/' . $name);
        }

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate) && filesize($candidate) > 0) {
                return $candidate;
            }
        }

        // Búsqueda por basename en boletas/ (por si la carpeta tiene subdirs).
        foreach ([storage_path('app/public/boletas'), storage_path('app/boletas')] as $dir) {
            $found = $this->findBasenameInDir($dir, $baseSan !== '' ? $baseSan : $base);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function findBasenameInDir(string $dir, string $basename): ?string
    {
        if ($basename === '' || !is_dir($dir)) {
            return null;
        }

        $target = strtolower($basename);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if (strcasecmp($file->getFilename(), $basename) === 0 || strtolower($file->getFilename()) === $target) {
                return $file->getPathname();
            }
        }

        return null;
    }

    private function normalizeIncomingPath(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        // URL completa → path
        if (preg_match('#^https?://#i', $input)) {
            $path = parse_url($input, PHP_URL_PATH);
            $input = is_string($path) ? $path : $input;
        }

        $input = rawurldecode($input);
        $normalized = $this->storage->normalizeRelativePath($input);
        if ($normalized !== null && $normalized !== '') {
            // Si solo viene el basename, asumir boletas/
            if (!str_contains($normalized, '/') && preg_match('/\.(pdf|xlsx|xlsm)$/i', $normalized)) {
                if (stripos($normalized, 'COTIZACION_INICIAL_') === 0) {
                    $ext = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
                    $normalized = ($ext === 'pdf' ? 'boletas/' : 'templates/') . $normalized;
                } else {
                    $normalized = 'boletas/' . $normalized;
                }
            }

            return $normalized;
        }

        return null;
    }

    private function isCotizacionInicialPath(string $relative): bool
    {
        $relative = strtolower(str_replace('\\', '/', $relative));
        $base = basename($relative);

        if (str_starts_with($relative, 'boletas/')) {
            return true;
        }

        if (str_contains($base, 'cotizacion_inicial')) {
            return true;
        }

        return false;
    }

    /** @return array<int, string> */
    private function columnNames(string $column): array
    {
        if ($column === 'excel') {
            return ['url_cotizacion'];
        }
        if ($column === 'both') {
            return ['url_cotizacion_pdf', 'url_cotizacion'];
        }

        return ['url_cotizacion_pdf'];
    }

    private function cdnBase(): string
    {
        return rtrim((string) config('object_storage.cdn_base_url', ''), '/');
    }

    private function cdnUrlFor(string $relative): string
    {
        $base = $this->cdnBase();
        if ($base === '') {
            $base = 'https://cdn.probusiness.pe';
        }

        $encoded = StoragePathSanitizer::encodeRelativePathForUrl($relative);

        return $base . '/' . ltrim($encoded, '/');
    }

    private function cdnHeadOk(string $relative): bool
    {
        $url = $this->cdnUrlFor($relative);

        try {
            $response = Http::timeout(15)->withOptions(['allow_redirects' => true])->head($url);
            $status = $response->status();
            if (($status >= 200 && $status < 400) || $status === 206) {
                return true;
            }

            // Algunos CDN no permiten HEAD
            $get = Http::timeout(15)->withHeaders(['Range' => 'bytes=0-0'])->get($url);
            $getStatus = $get->status();

            return ($getStatus >= 200 && $getStatus < 400) || $getStatus === 206;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function exportMissingCsv(string $exportPath): void
    {
        $dir = dirname($exportPath);
        if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $handle = fopen($exportPath, 'wb');
        if ($handle === false) {
            $this->error('No se pudo escribir: ' . $exportPath);

            return;
        }

        fputcsv($handle, ['id_calculadora', 'cliente', 'column', 'db_path', 'cdn_url']);
        foreach ($this->missingRows as $missingRow) {
            fputcsv($handle, [
                $missingRow['id'],
                $missingRow['cliente'],
                $missingRow['column'],
                $missingRow['db_path'],
                $missingRow['cdn_url'],
            ]);
        }
        fclose($handle);
        $this->info('Exportados ' . count($this->missingRows) . ' MISSING → ' . $exportPath);
    }

    private function uploadDisk(): string
    {
        return (string) config('object_storage.upload_disk', 'local');
    }
}
