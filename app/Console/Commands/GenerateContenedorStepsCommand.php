<?php

namespace App\Console\Commands;

use App\Http\Controllers\CargaConsolidada\ContenedorController;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\ContenedorPasos;
use Illuminate\Console\Command;

class GenerateContenedorStepsCommand extends Command
{
    protected $signature = 'contenedor:generate-steps
                            {id : ID del contenedor}
                            {--force : Elimina los pasos existentes del contenedor y los regenera}';

    protected $description = 'Genera los pasos (COTIZADOR, DOCUMENTACION, etc.) de un contenedor, igual que al crearlo en store()';

    public function handle(ContenedorController $contenedorController): int
    {
        $id = (int) $this->argument('id');

        if ($id <= 0) {
            $this->error('El ID del contenedor debe ser un entero positivo.');

            return self::FAILURE;
        }

        $contenedor = Contenedor::query()->find($id);
        if ($contenedor === null) {
            $this->error("Contenedor #{$id} no encontrado.");

            return self::FAILURE;
        }

        $existentes = ContenedorPasos::query()->where('id_pedido', $id)->count();

        if ($existentes > 0 && !$this->option('force')) {
            $this->warn("El contenedor #{$id} ya tiene {$existentes} paso(s). Usa --force para borrarlos y regenerarlos.");

            return self::FAILURE;
        }

        if ($existentes > 0 && $this->option('force')) {
            $eliminados = ContenedorPasos::query()->where('id_pedido', $id)->delete();
            $this->line("Eliminados {$eliminados} paso(s) existentes.");
        }

        try {
            $contenedorController->generateSteps($id);
        } catch (\Throwable $e) {
            $this->error('Error al generar pasos: ' . $e->getMessage());

            return self::FAILURE;
        }

        $total = ContenedorPasos::query()->where('id_pedido', $id)->count();
        $this->info("Pasos generados correctamente para contenedor #{$id} ({$total} registros).");

        return self::SUCCESS;
    }
}
