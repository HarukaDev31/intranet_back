<?php

namespace App\Console\Commands;

use App\Contracts\ObjectStorageConnectorInterface;
use App\Models\CargaConsolidada\ConsolidadoPlantillaFinalBatch;
use App\Support\Storage\StoragePathSanitizer;
use App\Traits\UsesObjectStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

class UploadMissingCotizacionFinalStorageCommand extends Command
{
    use UsesObjectStorage;

    protected $signature = 'storage:upload-cotizacion-final-missing
                            {idContenedor?}
                            {--all : Procesar todos los MISSING detectados}
                            {--csv= : CSV exportado por storage:audit-cotizacion-final}
                            {--from-temp : Buscar en storage/app/temp}
                            {--from-local : Buscar en storage/app/cotizacion_final y public}
                            {--from-batch-zips : Extraer de plantillas-finales/zips en S3}
                            {--dry-run : Simular sin subir}
                            {--limit=0 : Máximo de filas a procesar (0 = sin límite)}';

    protected $description = 'Sube a S3 cotizaciones finales MISSING usando archivos en temp, disco local o ZIPs de batch';

    /** @var ObjectStorageConnectorInterface */
    private $storage;

    /** @var array<string, string> basename (lower) => absolute path */
    private $tempIndex = [];

    /** @var array<string, string> */
    private $localIndex = [];

    /** @var array<int, string> id_cotizacion => absolute path */
    private $zipExtractByCotizacionId = [];

    /** @var array<string, string> "{contenedor}:{cliente_normalizado}" => absolute path */
    private $clienteIndex = [];

    /** @var int */
    private $uploaded = 0;

    /** @var int */
    private $notFound = 0;

    /** @var int */
    private $skippedOk = 0;

    /** @var int */
    private $failed = 0;

    public function handle(ObjectStorageConnectorInterface $storage): int
    {
        $this->storage = $storage;

        if ($this->uploadDisk() !== 's3') {
            $this->error('FILESYSTEM_UPLOAD_DISK debe ser "s3". Valor actual: ' . $this->uploadDisk());

            return self::FAILURE;
        }

        $fromTemp = (bool) $this->option('from-temp');
        $fromLocal = (bool) $this->option('from-local');
        $fromZips = (bool) $this->option('from-batch-zips');

        if (!$fromTemp && !$fromLocal && !$fromZips) {
            $fromTemp = true;
            $fromLocal = true;
            $fromZips = true;
            $this->comment('Usando todas las fuentes: temp, local y batch-zips.');
        }

        $rows = $this->loadRows();
        if ($rows === []) {
            $this->info('No hay cotizaciones MISSING para procesar.');

            return self::SUCCESS;
        }

        $this->info('Candidatas MISSING: ' . count($rows));

        if ($fromTemp) {
            $this->buildTempIndex();
            $this->line('Índice temp: ' . count($this->tempIndex) . ' claves basename');
        }

        if ($fromLocal) {
            $this->buildLocalIndex();
            $this->line('Índice local cotizacion_final: ' . count($this->localIndex) . ' claves basename');
        }

        if ($fromZips) {
            $contenedorIds = array_values(array_unique(array_map(static fn ($r) => (int) $r['id_contenedor'], $rows)));
            foreach ($contenedorIds as $contenedorId) {
                $this->indexBatchZipsForContenedor($contenedorId);
            }
            $this->line('Índice batch-zips: ' . count($this->zipExtractByCotizacionId) . ' por id_cotizacion');
            $this->line('Índice por cliente: ' . count($this->clienteIndex) . ' claves contenedor+cliente');
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $processed = 0;

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $this->processRow($row, $dryRun);
            $bar->advance();
            $processed++;
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Subidos a S3', $this->uploaded],
                ['Ya OK en S3 (omitidos)', $this->skippedOk],
                ['Sin archivo fuente', $this->notFound],
                ['Errores', $this->failed],
            ]
        );

        if (!$dryRun && $this->uploaded > 0) {
            $this->comment('Verificar: php artisan storage:audit-cotizacion-final --all');
        }

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array{id: int, id_contenedor: int, nombre: string, cotizacion_final_url: string}>
     */
    private function loadRows(): array
    {
        $csvPath = trim((string) $this->option('csv'));
        if ($csvPath !== '') {
            return $this->loadRowsFromCsv($csvPath);
        }

        $all = (bool) $this->option('all');
        $idContenedor = $this->argument('idContenedor');

        if (!$all && ($idContenedor === null || $idContenedor === '')) {
            $this->error('Indica idContenedor, --all o --csv=');

            exit(self::FAILURE);
        }

        $query = DB::table('contenedor_consolidado_cotizacion')
            ->whereNotNull('cotizacion_final_url')
            ->where('cotizacion_final_url', '!=', '')
            ->select('id', 'id_contenedor', 'nombre', 'cotizacion_final_url')
            ->orderBy('id');

        if (!$all) {
            $query->where('id_contenedor', (int) $idContenedor);
        }

        $rows = [];
        foreach ($query->cursor() as $row) {
            if ($this->isMissingOnStorage((string) $row->cotizacion_final_url)) {
                $rows[] = [
                    'id' => (int) $row->id,
                    'id_contenedor' => (int) $row->id_contenedor,
                    'nombre' => (string) $row->nombre,
                    'cotizacion_final_url' => trim(str_replace('\\', '/', (string) $row->cotizacion_final_url)),
                ];
            } else {
                $this->skippedOk++;
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array{id: int, id_contenedor: int, nombre: string, cotizacion_final_url: string}>
     */
    private function loadRowsFromCsv(string $csvPath): array
    {
        if (!is_file($csvPath)) {
            $this->error('CSV no encontrado: ' . $csvPath);

            exit(self::FAILURE);
        }

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            $this->error('No se pudo leer CSV: ' . $csvPath);

            exit(self::FAILURE);
        }

        $header = fgetcsv($handle);
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 4) {
                continue;
            }
            $rows[] = [
                'id' => (int) $data[0],
                'id_contenedor' => (int) $data[1],
                'nombre' => (string) $data[2],
                'cotizacion_final_url' => trim(str_replace('\\', '/', (string) $data[3])),
            ];
        }
        fclose($handle);

        return $rows;
    }

    private function isMissingOnStorage(string $dbPath): bool
    {
        $dbPath = trim(str_replace('\\', '/', $dbPath));
        $sanitized = StoragePathSanitizer::relativePath($dbPath);

        if ($this->storage->existsOnS3($dbPath) || ($sanitized !== $dbPath && $this->storage->existsOnS3($sanitized))) {
            return false;
        }

        foreach ([
            storage_path('app/public/' . $dbPath),
            storage_path('app/' . $dbPath),
            storage_path('app/public/' . $sanitized),
            storage_path('app/' . $sanitized),
        ] as $localPath) {
            if (is_file($localPath)) {
                return false;
            }
        }

        return true;
    }

    private function buildTempIndex(): void
    {
        $this->indexDirectory(storage_path('app/temp'), $this->tempIndex);
    }

    private function buildLocalIndex(): void
    {
        foreach ([storage_path('app/cotizacion_final'), storage_path('app/public/cotizacion_final')] as $root) {
            $this->indexDirectory($root, $this->localIndex);
        }
    }

    /**
     * @param array<string, string> $basenameIndex
     */
    private function indexDirectory(string $root, array &$basenameIndex): void
    {
        if (!is_dir($root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $name = $fileInfo->getFilename();
            if (!preg_match('/\.xlsx$/i', $name)) {
                continue;
            }

            $this->registerBasenameIndex($basenameIndex, $name, $fileInfo->getPathname());
            $this->registerClienteIndex($name, $fileInfo->getPathname(), $this->extractContenedorFromPath($fileInfo->getPathname()));
        }
    }

    private function registerClienteIndex(string $basename, string $absolutePath, ?int $idContenedor = null): void
    {
        $clienteKey = $this->clienteKeyFromCotizacionFilename($basename);
        if ($clienteKey === null) {
            return;
        }

        if ($idContenedor !== null && $idContenedor > 0) {
            $this->clienteIndex[$idContenedor . ':' . $clienteKey] = $absolutePath;
        }

        if (!isset($this->clienteIndex['0:' . $clienteKey])) {
            $this->clienteIndex['0:' . $clienteKey] = $absolutePath;
        }
    }

    private function normalizeClienteKey(string $name): string
    {
        $name = preg_replace('/[\s\x{00A0}]+/u', '_', trim($name));
        $name = preg_replace('/_+/', '_', $name);

        return strtolower(preg_replace('/[^a-z0-9_]/', '_', $name));
    }

    private function clienteKeyFromCotizacionFilename(string $basename): ?string
    {
        if (!preg_match('/^Cotizacion(.+)_(\d{14})\.xlsx$/i', $basename, $matches)) {
            return null;
        }

        return $this->normalizeClienteKey($matches[1]);
    }

    private function clienteMatchKey(int $idContenedor, string $nombre): string
    {
        return $idContenedor . ':' . $this->normalizeClienteKey($nombre);
    }

    private function extractContenedorFromPath(string $absolutePath): ?int
    {
        $normalized = str_replace('\\', '/', $absolutePath);
        if (preg_match('#/cotizacion_final/(\d+)/#', $normalized, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @param array<string, string> $index
     */
    private function registerBasenameIndex(array &$index, string $basename, string $absolutePath): void
    {
        $keys = [
            strtolower($basename),
            strtolower(StoragePathSanitizer::fileName($basename)),
        ];

        foreach (array_unique($keys) as $key) {
            if ($key !== '' && !isset($index[$key])) {
                $index[$key] = $absolutePath;
            }
        }
    }

    private function indexBatchZipsForContenedor(int $idContenedor): void
    {
        $batches = ConsolidadoPlantillaFinalBatch::query()
            ->where('id_contenedor', $idContenedor)
            ->where('estado', 'COMPLETED')
            ->whereNotNull('zip_path')
            ->orderByDesc('id')
            ->get(['id', 'zip_path', 'detalle_json']);

        foreach ($batches as $batch) {
            $zipPath = ltrim((string) $batch->zip_path, '/');
            if ($zipPath === '' || !$this->storage->exists($zipPath)) {
                continue;
            }

            try {
                $localZip = $this->storageLocalPath($zipPath);
            } catch (\Throwable $e) {
                $this->warn('No se pudo leer ZIP batch #' . $batch->id . ': ' . $e->getMessage());

                continue;
            }

            $zip = new ZipArchive();
            if ($zip->open($localZip) !== true) {
                continue;
            }

            $detalle = is_array($batch->detalle_json) ? $batch->detalle_json : [];
            $exitosos = is_array($detalle['exitosos'] ?? null) ? $detalle['exitosos'] : [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                if (!is_string($entryName) || !preg_match('/\.xlsx$/i', $entryName)) {
                    continue;
                }

                $basename = basename(str_replace('\\', '/', $entryName));
                $tmpFile = tempnam(sys_get_temp_dir(), 'cot_fin_zip_');
                if ($tmpFile === false) {
                    continue;
                }
                $tmpXlsx = $tmpFile . '.xlsx';
                @unlink($tmpFile);

                $contents = $zip->getFromIndex($i);
                if ($contents === false || file_put_contents($tmpXlsx, $contents) === false) {
                    @unlink($tmpXlsx);

                    continue;
                }

                $idCotizacion = $this->matchCotizacionIdFromDetalle($exitosos, $basename);
                if ($idCotizacion > 0 && !isset($this->zipExtractByCotizacionId[$idCotizacion])) {
                    $this->zipExtractByCotizacionId[$idCotizacion] = $tmpXlsx;
                }

                $this->registerClienteIndex($basename, $tmpXlsx, $idContenedor);
            }

            $zip->close();
        }
    }

    /**
     * @param array<int, array<string, mixed>> $exitosos
     */
    private function matchCotizacionIdFromDetalle(array $exitosos, string $basename): int
    {
        $basenameLower = strtolower($basename);
        $sanitizedLower = strtolower(StoragePathSanitizer::fileName($basename));

        foreach ($exitosos as $item) {
            if (!is_array($item)) {
                continue;
            }

            $archivo = isset($item['archivo']) ? strtolower((string) $item['archivo']) : '';
            if ($archivo === $basenameLower || $archivo === $sanitizedLower) {
                return (int) ($item['id_cotizacion'] ?? 0);
            }

            $nombre = isset($item['nombre']) ? (string) $item['nombre'] : '';
            $fileClienteKey = $this->clienteKeyFromCotizacionFilename($basename);
            if ($fileClienteKey !== null && $nombre !== ''
                && $this->normalizeClienteKey($nombre) === $fileClienteKey) {
                return (int) ($item['id_cotizacion'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * @param array{id: int, id_contenedor: int, nombre: string, cotizacion_final_url: string} $row
     */
    private function processRow(array $row, bool $dryRun): void
    {
        $dbPath = $row['cotizacion_final_url'];
        $targetPath = StoragePathSanitizer::relativePath($dbPath);
        $basename = basename($dbPath);

        $sourcePath = $this->findSourcePath($row, $basename);
        if ($sourcePath === null) {
            $this->notFound++;

            return;
        }

        try {
            if ($dryRun) {
                $this->uploaded++;
                $this->line('');
                $this->line("[dry-run] id={$row['id']} ← {$sourcePath} → s3://{$targetPath}");

                return;
            }

            $storedPath = $this->storagePutContents($targetPath, (string) file_get_contents($sourcePath));

            if ($storedPath !== $dbPath) {
                DB::table('contenedor_consolidado_cotizacion')
                    ->where('id', $row['id'])
                    ->update(['cotizacion_final_url' => $storedPath]);
            }

            $this->uploaded++;
        } catch (\Throwable $e) {
            $this->failed++;
            $this->newLine();
            $this->error("id={$row['id']}: " . $e->getMessage());
        }
    }

    /**
     * @param array{id: int, id_contenedor: int, nombre: string, cotizacion_final_url: string} $row
     */
    private function findSourcePath(array $row, string $basename): ?string
    {
        $keys = [
            strtolower($basename),
            strtolower(StoragePathSanitizer::fileName($basename)),
        ];

        foreach ([
            storage_path('app/' . $row['cotizacion_final_url']),
            storage_path('app/public/' . $row['cotizacion_final_url']),
            storage_path('app/' . StoragePathSanitizer::relativePath($row['cotizacion_final_url'])),
            storage_path('app/public/' . StoragePathSanitizer::relativePath($row['cotizacion_final_url'])),
        ] as $direct) {
            if (is_file($direct)) {
                return $direct;
            }
        }

        foreach ($keys as $key) {
            if (isset($this->localIndex[$key])) {
                return $this->localIndex[$key];
            }
            if (isset($this->tempIndex[$key])) {
                return $this->tempIndex[$key];
            }
        }

        if (isset($this->zipExtractByCotizacionId[$row['id']])) {
            return $this->zipExtractByCotizacionId[$row['id']];
        }

        $clienteKeys = [
            $this->clienteMatchKey($row['id_contenedor'], $row['nombre']),
            $this->clienteMatchKey($row['id_contenedor'], basename($row['cotizacion_final_url'], '.xlsx')),
        ];
        $fileClienteKey = $this->clienteKeyFromCotizacionFilename($basename);
        if ($fileClienteKey !== null) {
            $clienteKeys[] = $row['id_contenedor'] . ':' . $fileClienteKey;
            $clienteKeys[] = '0:' . $fileClienteKey;
        }

        foreach (array_unique($clienteKeys) as $clienteKey) {
            if (isset($this->clienteIndex[$clienteKey])) {
                return $this->clienteIndex[$clienteKey];
            }
        }

        return null;
    }

    private function uploadDisk(): string
    {
        return (string) config('object_storage.upload_disk', 'local');
    }
}
