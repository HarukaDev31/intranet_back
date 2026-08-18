<?php

namespace App\Console\Commands;

use App\Services\ManualUsuario\ManualUsuarioCapturasAttacher;
use Illuminate\Console\Command;

class ManualAttachCapturasCommand extends Command
{
    protected $signature = 'manual:attach-capturas
                            {--dry-run : Valida y muestra el plan sin subir ni enlazar}
                            {--strict : Falla antes de escribir si falta una clave/PNG o hay PNG huérfanos}
                            {--legacy : Habilita explícitamente el resolvedor heurístico de transición}
                            {--directory= : Directorio fuente; por defecto resources/manual/capturas}';

    protected $description = 'Sube capturas de resources/manual/capturas y las enlaza a bloques media del CMS';

    public function handle(ManualUsuarioCapturasAttacher $attacher)
    {
        try {
            $result = $attacher->attach([
                'dry_run' => (bool) $this->option('dry-run'),
                'strict' => (bool) $this->option('strict'),
                'legacy' => (bool) $this->option('legacy'),
                'directory' => $this->option('directory') ?: resource_path('manual/capturas'),
            ]);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result['dry_run']) {
            $this->info('Dry-run. Subiría: ' . $result['would_upload']
                . '. Enlazaría: ' . $result['would_link']
                . '. Omitidas: ' . $result['skipped'] . '.');
        } else {
            $this->info('Capturas subidas: ' . $result['uploaded']
                . '. Enlazadas: ' . $result['linked']
                . '. Omitidas: ' . $result['skipped'] . '.');
        }
        if (!empty($result['shared_keys'])) {
            $this->info('Claves compartidas: ' . $result['shared_keys']
                . ' (' . $result['shared_blocks'] . ' bloques).');
        }
        if ($result['issues']) {
            $this->warn('Incidencias: ' . count($result['issues'])
                . '. Ejecuta manual:audit-capturas para el detalle.');
        }

        return self::SUCCESS;
    }
}
