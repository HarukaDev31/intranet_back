<?php

namespace App\Console\Commands;

use App\Contracts\ObjectStorageConnectorInterface;
use App\Support\Storage\StoragePathSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditCotizacionFinalStorageCommand extends Command
{
    protected $signature = 'storage:audit-cotizacion-final
                            {idContenedor?}
                            {--all : Auditar todas las cotizaciones con cotizacion_final_url}
                            {--limit=0 : Máximo de filas a procesar (0 = sin límite)}
                            {--details : Listar cada cotización con su estado}';

    protected $description = 'Audita cotizacion_final_url en BD vs archivos en S3 y disco local legacy';

    /** @var ObjectStorageConnectorInterface */
    private $storage;

    /** @var array<string, int> */
    private $stats = [
        'total' => 0,
        'ok_s3' => 0,
        'ok_s3_sanitized_only' => 0,
        'pending_local' => 0,
        'missing' => 0,
    ];

    public function handle(ObjectStorageConnectorInterface $storage): int
    {
        $this->storage = $storage;

        $idContenedor = $this->argument('idContenedor');
        $all = (bool) $this->option('all');

        if (!$all && ($idContenedor === null || $idContenedor === '')) {
            $this->error('Indica idContenedor o usa --all.');

            return self::FAILURE;
        }

        $query = DB::table('contenedor_consolidado_cotizacion')
            ->whereNotNull('cotizacion_final_url')
            ->where('cotizacion_final_url', '!=', '')
            ->select('id', 'id_contenedor', 'nombre', 'cotizacion_final_url')
            ->orderBy('id_contenedor')
            ->orderBy('id');

        if (!$all) {
            $query->where('id_contenedor', (int) $idContenedor);
            $this->info('Auditoría cotización final — contenedor ' . (int) $idContenedor);
        } else {
            $this->info('Auditoría cotización final — todos los contenedores');
        }

        $limit = max(0, (int) $this->option('limit'));
        $details = (bool) $this->option('details');
        $processed = 0;

        $query->chunk(200, function ($rows) use ($limit, $details, &$processed) {
            foreach ($rows as $row) {
                if ($limit > 0 && $processed >= $limit) {
                    return false;
                }

                $this->auditRow($row, $details);
                $processed++;
            }
        });

        $this->newLine();
        $this->table(
            ['Estado', 'Cantidad', 'Descripción'],
            [
                ['Total en BD', $this->stats['total'], 'Registros con cotizacion_final_url'],
                ['OK en S3', $this->stats['ok_s3'], 'Archivo encontrado en S3 (ruta BD o sanitizada)'],
                ['S3 solo ruta sanitizada', $this->stats['ok_s3_sanitized_only'], 'En S3 con clave sanitizada; BD tiene espacios/caracteres distintos'],
                ['Pendiente migrar', $this->stats['pending_local'], 'Existe en disco local, falta en S3'],
                ['Sin archivo', $this->stats['missing'], 'No está en S3 ni en disco local'],
            ]
        );

        if ($this->stats['pending_local'] > 0) {
            $this->line('');
            $this->comment('Migrar pendientes: php artisan storage:migrate-local-to-s3 --subdir=cotizacion_final');
        }

        return self::SUCCESS;
    }

    /**
     * @param object{id: int, id_contenedor: int, nombre: string, cotizacion_final_url: string} $row
     */
    private function auditRow(object $row, bool $details): void
    {
        $this->stats['total']++;

        $dbPath = trim(str_replace('\\', '/', (string) $row->cotizacion_final_url));
        $sanitized = StoragePathSanitizer::relativePath($dbPath);

        $onS3Raw = $this->storage->existsOnS3($dbPath);
        $onS3Sanitized = !$onS3Raw && $sanitized !== $dbPath && $this->storage->existsOnS3($sanitized);

        $localPublic = storage_path('app/public/' . $dbPath);
        $localApp = storage_path('app/' . $dbPath);
        $localSanitizedPublic = $sanitized !== $dbPath ? storage_path('app/public/' . $sanitized) : null;
        $localSanitizedApp = $sanitized !== $dbPath ? storage_path('app/' . $sanitized) : null;

        $hasLocal = is_file($localPublic)
            || is_file($localApp)
            || ($localSanitizedPublic !== null && is_file($localSanitizedPublic))
            || ($localSanitizedApp !== null && is_file($localSanitizedApp));

        if ($onS3Raw) {
            $status = 'OK_S3';
            $this->stats['ok_s3']++;
        } elseif ($onS3Sanitized) {
            $status = 'S3_SANITIZED_ONLY';
            $this->stats['ok_s3']++;
            $this->stats['ok_s3_sanitized_only']++;
        } elseif ($hasLocal) {
            $status = 'PENDING_LOCAL';
            $this->stats['pending_local']++;
        } else {
            $status = 'MISSING';
            $this->stats['missing']++;
        }

        if (!$details) {
            return;
        }

        $line = sprintf(
            'id=%d cont=%d | %s | local=%s | s3=%s | %s',
            $row->id,
            $row->id_contenedor,
            $status,
            $hasLocal ? 'SI' : 'NO',
            ($onS3Raw || $onS3Sanitized) ? 'SI' : 'NO',
            $dbPath
        );

        if ($status === 'S3_SANITIZED_ONLY') {
            $line .= ' | s3_key=' . $sanitized;
        }

        $this->line($line);
    }
}
