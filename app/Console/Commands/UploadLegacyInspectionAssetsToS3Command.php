<?php

namespace App\Console\Commands;

use App\Support\Storage\LegacyInspectionAssets;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Sube al bucket S3 (prod) los archivos legacy de inspección recuperados del proyecto
 * CodeIgniter (assets/images/) bajo assets/imgs/, conservando el nombre original
 * (incluye caracteres chinos y subcarpetas como agentecompra/).
 *
 * Usa las credenciales AWS ya configuradas en el .env de la app (disco s3), pero escribe
 * en la raíz del bucket (ignora AWS_UPLOAD_PREFIX) para que la URL sea CDN + assets/imgs/...
 *
 * Ejemplos:
 *   php artisan legacy:upload-inspection-assets /root/restaurados/assets/images --dry-run
 *   php artisan legacy:upload-inspection-assets /root/restaurados/assets/images
 *   php artisan legacy:upload-inspection-assets /root/restaurados/assets/images --update-db
 */
class UploadLegacyInspectionAssetsToS3Command extends Command
{
    protected $signature = 'legacy:upload-inspection-assets
        {source=/root/restaurados/assets/images : Carpeta local con los archivos recuperados (equivale a assets/images/)}
        {--prefix=assets/imgs : Prefijo (key) dentro del bucket}
        {--dry-run : Listar lo que se subiría sin subir ni tocar BD}
        {--force : Sobrescribir objetos que ya existen en S3}
        {--update-db : Tras subir, reescribir file_path de las filas legacy cuyo archivo quedó en S3}
        {--limit=0 : Máximo de archivos a procesar (0 = sin límite)}';

    protected $description = 'Sube los assets legacy de inspección (assets/images) recuperados a S3 en assets/imgs/ y opcionalmente actualiza file_path a ruta relativa';

    private const TABLE = 'contenedor_consolidado_almacen_inspection';

    /** @var int */
    private $uploaded = 0;

    /** @var int */
    private $skippedExisting = 0;

    /** @var int */
    private $failed = 0;

    /** @var array<string, bool> keys (relativas a assets/images) presentes en S3 tras la corrida */
    private $available = [];

    public function handle(): int
    {
        $source = rtrim(str_replace('\\', '/', (string) $this->argument('source')), '/');
        $prefix = trim(str_replace('\\', '/', (string) $this->option('prefix')), '/');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $limit = max(0, (int) $this->option('limit'));

        if (!is_dir($source)) {
            $this->error("La carpeta origen no existe: {$source}");

            return self::FAILURE;
        }

        if ($prefix === '') {
            $this->error('--prefix no puede estar vacío');

            return self::FAILURE;
        }

        if ($prefix !== LegacyInspectionAssets::PREFIX) {
            $this->warn('Ojo: el back solo resuelve vía CDN rutas bajo ' . LegacyInspectionAssets::PREFIX . '/');
        }

        $config = config('filesystems.disks.s3');
        if (!is_array($config) || empty($config['bucket']) || empty($config['key'])) {
            $this->error('Disco s3 sin configurar (AWS_ACCESS_KEY_ID / AWS_BUCKET en .env)');

            return self::FAILURE;
        }

        // Mismas credenciales que la app, pero en la raíz del bucket (sin AWS_UPLOAD_PREFIX)
        $config['root'] = '';
        $config['throw'] = true;
        $disk = Storage::build($config);

        try {
            $disk->directories('/');
        } catch (\Throwable $e) {
            $this->error('No se pudo conectar al bucket: ' . $e->getMessage());

            return self::FAILURE;
        }

        $files = $this->collectFiles($source);
        $total = count($files);

        $this->info('Assets legacy de inspección → S3');
        $this->line('Bucket:  ' . $config['bucket']);
        $this->line("Destino: s3://{$config['bucket']}/{$prefix}/");
        $this->line("Origen:  {$source}");
        $this->line('CDN:     ' . rtrim((string) config('object_storage.cdn_base_url', ''), '/') . "/{$prefix}/...");
        $this->info("Archivos encontrados: {$total}");
        if ($dryRun) {
            $this->warn('Modo dry-run: no se sube nada ni se toca la BD.');
        }

        if ($total === 0) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($limit > 0 ? min($limit, $total) : $total);
        $bar->start();

        $processed = 0;
        foreach ($files as $relative => $absolute) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $this->processFile($disk, $absolute, $relative, $prefix . '/' . $relative, $dryRun, $force);
            $bar->advance();
            $processed++;
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Métrica', 'Cantidad'], [
            [$dryRun ? 'Se subirían' : 'Subidos', $this->uploaded],
            ['Ya existían en S3 (omitidos)', $this->skippedExisting],
            ['Errores', $this->failed],
        ]);

        if ($this->option('update-db')) {
            $this->updateDatabase($prefix, $dryRun);
        }

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, string> relativa (respecto a source) => ruta absoluta
     */
    private function collectFiles(string $source): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $info */
        foreach ($iterator as $info) {
            if (!$info->isFile() || $info->getSize() === 0) {
                continue;
            }

            $absolute = str_replace('\\', '/', $info->getPathname());
            $relative = ltrim(substr($absolute, strlen($source)), '/');
            if ($relative === '' || $relative[0] === '.') {
                continue;
            }

            $files[$relative] = $absolute;
        }

        ksort($files);

        return $files;
    }

    private function processFile(Filesystem $disk, string $absolute, string $relative, string $key, bool $dryRun, bool $force): void
    {
        try {
            if (!$force && $disk->exists($key)) {
                $this->skippedExisting++;
                $this->available[$relative] = true;

                return;
            }

            if ($dryRun) {
                $this->uploaded++;
                $this->available[$relative] = true;

                return;
            }

            $stream = fopen($absolute, 'rb');
            if ($stream === false) {
                throw new \RuntimeException('No se pudo abrir el archivo');
            }

            $options = [];
            $mime = function_exists('mime_content_type') ? @mime_content_type($absolute) : false;
            if (is_string($mime) && $mime !== '' && $mime !== 'application/octet-stream') {
                $options['ContentType'] = $mime;
            }

            try {
                $disk->writeStream($key, $stream, $options);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $this->uploaded++;
            $this->available[$relative] = true;
        } catch (\Throwable $e) {
            $this->failed++;
            $this->newLine();
            $this->error("Fallo {$key}: " . $e->getMessage());
        }
    }

    /**
     * Reescribe file_path solo de las filas cuyo archivo está en S3 (subido o ya existente).
     * Las filas cuyo archivo no se recuperó se dejan como están.
     */
    private function updateDatabase(string $prefix, bool $dryRun): void
    {
        $this->newLine();
        $this->info('Actualizando file_path en ' . self::TABLE . ($dryRun ? ' (dry-run)' : ''));

        $updated = 0;
        $missing = 0;

        DB::table(self::TABLE)
            ->where('file_path', 'like', '%' . LegacyInspectionAssets::LEGACY_MARKER . '%')
            ->select(['id', 'file_path'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($prefix, $dryRun, &$updated, &$missing) {
                foreach ($rows as $row) {
                    $name = LegacyInspectionAssets::legacyRelativeName($row->file_path);
                    if ($name === null || !isset($this->available[$name])) {
                        $missing++;

                        continue;
                    }

                    if (!$dryRun) {
                        DB::table(self::TABLE)->where('id', $row->id)->update([
                            'file_path' => $prefix . '/' . $name,
                        ]);
                    }
                    $updated++;
                }
            });

        $this->table(['Filas', 'Cantidad'], [
            [$dryRun ? 'Se actualizarían' : 'Actualizadas', $updated],
            ['Sin archivo en S3 (sin cambios)', $missing],
        ]);
    }
}
