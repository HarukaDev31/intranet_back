<?php

namespace App\Console\Commands;

use App\Services\ManualUsuario\ManualUsuarioCapturasAttacher;
use App\Services\ManualUsuario\ManualUsuarioCmsPlantillaMigrator;
use App\Services\ManualUsuario\ManualUsuarioCursoAlumnosSeeder;
use App\Services\ManualUsuario\ManualUsuarioRoleManualSeeder;
use Illuminate\Console\Command;

class ManualMigratePlantillaCommand extends Command
{
    protected $signature = 'manual:migrate-plantilla
                            {--slug= : Solo un rol (ej. jefe-importacion)}
                            {--key= : Solo un modulo_key o prefijo con *}
                            {--from-catalog : Re-siembra desde ScreensCatalog (reemplaza bloques; luego re-enlazar capturas)}
                            {--in-place : Migra textos/orden en CMS sin borrar bloques ni media_id (default si no --from-catalog)}
                            {--keep-cuando : Conserva el bloque ¿Cuándo utilizarlo?}
                            {--attach-capturas : Tras migrar, ejecuta manual:attach-capturas para el mismo alcance}
                            {--dry-run : Solo muestra qué haría (solo válido con --from-catalog usando seed dry - no implementado; use in-place sin dry)}';

    protected $description = 'Aplica la plantilla Alumnos (orden QA, viñetas, pasos numerados) a todo el manual';

    public function handle(
        ManualUsuarioCmsPlantillaMigrator $migrator,
        ManualUsuarioCapturasAttacher $attacher
    ): int {
        $slug = $this->option('slug') ?: null;
        $key = $this->normalizeKey($this->option('key'));
        $fromCatalog = (bool) $this->option('from-catalog');
        $dropCuando = !$this->option('keep-cuando');

        if ($fromCatalog) {
            $this->info('Re-siembra desde catálogo (estructura + textos del PHP)...');
            $seed = (new ManualUsuarioRoleManualSeeder())->seed($slug, $key);
            $this->line(sprintf(
                'Catálogo: %d páginas (%d nuevas, %d actualizadas, %d omitidas).',
                $seed['pages'],
                $seed['created'],
                $seed['updated'],
                $seed['skipped']
            ));

            if (!$slug && !$key) {
                (new ManualUsuarioCursoAlumnosSeeder())->seed();
                $this->line('Alumnos (Comercial) re-sembrado.');
            } elseif ($slug === 'comercial' && ($key === null || str_contains((string) $key, 'alumnos'))) {
                (new ManualUsuarioCursoAlumnosSeeder())->seed();
                $this->line('Alumnos (Comercial) re-sembrado.');
            }
        } else {
            $this->info('Migración in-place en CMS (conserva media_id y capture_key)...');
            $moduloKey = $key && !str_ends_with((string) $key, '*') ? $key : null;
            $stats = $migrator->migrate($slug, $moduloKey, $dropCuando);
            $this->table(
                ['Métrica', 'Total'],
                [
                    ['Páginas procesadas', $stats['pages']],
                    ['Bloques QA reordenados', $stats['qa_reordered']],
                    ['¿Para qué sirve? con viñetas', $stats['para_que_formatted']],
                    ['Pasos de flujo numerados', $stats['flow_bodies']],
                    ['¿Cuándo? eliminados', $stats['cuando_removed']],
                ]
            );

            if ($key && str_ends_with((string) $key, '*')) {
                $this->warn('Prefijo de --key ignorado en in-place; usa --from-catalog --key= prefijo* para filtrar catálogo.');
            }
        }

        if ($this->option('attach-capturas')) {
            $this->info('Re-enlazando capturas por capture_key...');
            try {
                $result = $attacher->attach([
                    'dry_run' => false,
                    'strict' => false,
                    'legacy' => true,
                    'directory' => resource_path('manual/capturas'),
                ]);
                $this->line('Enlazadas: ' . ($result['linked'] ?? 0) . '. Omitidas: ' . ($result['skipped'] ?? 0) . '.');
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->comment('Tip: in-place no reescribe textos del catálogo PHP; solo formatea lo ya publicado.');
        $this->comment('Para textos nuevos del catálogo: manual:migrate-plantilla --from-catalog --attach-capturas');

        return self::SUCCESS;
    }

    private function normalizeKey($key): ?string
    {
        if ($key === null || trim((string) $key) === '') {
            return null;
        }

        return trim((string) $key);
    }
}
