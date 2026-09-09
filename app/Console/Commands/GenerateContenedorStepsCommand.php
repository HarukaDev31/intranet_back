<?php

namespace App\Console\Commands;

use App\Http\Controllers\CargaConsolidada\ContenedorController;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\ContenedorPasos;
use Illuminate\Console\Command;

class GenerateContenedorStepsCommand extends Command
{
    protected $signature = 'contenedor:generate-steps
                            {id? : ID del contenedor (omitir si usas --all)}
                            {--all : Genera pasos para todos los contenedores}
                            {--force : Elimina los pasos existentes y los regenera}
                            {--only-missing : Con --all, solo contenedores sin pasos}';

    protected $description = 'Genera los pasos (COTIZADOR, DOCUMENTACION, etc.) de un contenedor, igual que al crearlo en store()';

    public function handle(ContenedorController $contenedorController): int
    {
        $all = (bool) $this->option('all');
        $idArg = $this->argument('id');

        if ($all && $idArg !== null && $idArg !== '') {
            $this->error('Usa {id} o --all, no ambos.');

            return self::FAILURE;
        }

        if (!$all && ($idArg === null || $idArg === '')) {
            $this->error('Indica el ID del contenedor o usa --all.');

            return self::FAILURE;
        }

        $ids = $all
            ? $this->resolveAllContenedorIds()
            : [(int) $idArg];

        if ($ids === []) {
            $this->warn('No hay contenedores para procesar.');

            return self::SUCCESS;
        }

        $ok = 0;
        $skipped = 0;
        $failed = 0;

        $this->info('Procesando ' . count($ids) . ' contenedor(es)…');
        $bar = $this->output->createProgressBar(count($ids));
        $bar->start();

        foreach ($ids as $id) {
            $result = $this->processOne($contenedorController, $id, !$all);
            if ($result === 'ok') {
                $ok++;
            } elseif ($result === 'skipped') {
                $skipped++;
            } else {
                $failed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Listo. ok={$ok} omitidos={$skipped} fallidos={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function resolveAllContenedorIds(): array
    {
        $query = Contenedor::query()->orderBy('id');

        if ($this->option('only-missing')) {
            $query->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('contenedor_consolidado_order_steps as s')
                    ->whereColumn('s.id_pedido', 'carga_consolidada_contenedor.id');
            });
        }

        return $query->pluck('id')->map(static fn ($id) => (int) $id)->all();
    }

    /**
     * @return 'ok'|'skipped'|'failed'
     */
    private function processOne(ContenedorController $contenedorController, int $id, bool $verbose): string
    {
        if ($id <= 0) {
            if ($verbose) {
                $this->error('El ID del contenedor debe ser un entero positivo.');
            }

            return 'failed';
        }

        $contenedor = Contenedor::query()->find($id);
        if ($contenedor === null) {
            if ($verbose) {
                $this->error("Contenedor #{$id} no encontrado.");
            } else {
                $this->newLine();
                $this->error("Contenedor #{$id} no encontrado.");
            }

            return 'failed';
        }

        $existentes = ContenedorPasos::query()->where('id_pedido', $id)->count();

        if ($existentes > 0 && !$this->option('force')) {
            if ($verbose) {
                $this->warn("El contenedor #{$id} ya tiene {$existentes} paso(s). Usa --force para borrarlos y regenerarlos.");
            }

            return 'skipped';
        }

        if ($existentes > 0 && $this->option('force')) {
            $eliminados = ContenedorPasos::query()->where('id_pedido', $id)->delete();
            if ($verbose) {
                $this->line("Eliminados {$eliminados} paso(s) existentes.");
            }
        }

        try {
            $contenedorController->generateSteps($id);
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("Error en contenedor #{$id}: " . $e->getMessage());

            return 'failed';
        }

        if ($verbose) {
            $total = ContenedorPasos::query()->where('id_pedido', $id)->count();
            $this->info("Pasos generados correctamente para contenedor #{$id} ({$total} registros).");
        }

        return 'ok';
    }
}
