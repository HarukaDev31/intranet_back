<?php

namespace App\Console\Commands;

use App\Models\CalculadoraImportacion;
use Illuminate\Console\Command;

class SyncCalculadoraCotizadoToConfirmado extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'calculadora:sync-cotizado-a-confirmado {--chunk=500 : Cantidad de filas por lote} {--dry-run : Solo simula sin guardar cambios}';

    /**
     * The console command description.
     */
    protected $description = 'Sincroniza calculadoras en estado COTIZADO a CONFIRMADO cuando su cotizacion vinculada tiene estado_cotizador CONFIRMADO.';

    public function handle(): int
    {
        $chunkSize = max((int) $this->option('chunk'), 1);
        $dryRun = (bool) $this->option('dry-run');

        $baseQuery = CalculadoraImportacion::query()
            ->where('estado', CalculadoraImportacion::ESTADO_COTIZADO)
            ->whereNotNull('id_cotizacion')
            ->whereHas('cotizacion', function ($query) {
                $query->where('estado_cotizador', 'CONFIRMADO');
            });

        $total = (clone $baseQuery)->count();

        if ($total === 0) {
            $this->info('No se encontraron filas de calculadora para sincronizar.');
            return self::SUCCESS;
        }

        $this->info("Filas encontradas para sincronizar: {$total}");
        if ($dryRun) {
            $this->warn('Modo simulacion activo: no se aplicaran cambios.');
        }

        $updated = 0;

        $baseQuery
            ->select('id')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$updated, $dryRun) {
                $ids = $rows->pluck('id')->all();
                if (empty($ids)) {
                    return;
                }

                if ($dryRun) {
                    $updated += count($ids);
                    return;
                }

                $updated += CalculadoraImportacion::query()
                    ->whereIn('id', $ids)
                    ->update(['estado' => CalculadoraImportacion::ESTADO_CONFIRMADO]);
            });

        if ($dryRun) {
            $this->info("Simulacion completada. Filas que se actualizarian: {$updated}");
            return self::SUCCESS;
        }

        $this->info("Sincronizacion completada. Filas actualizadas: {$updated}");

        return self::SUCCESS;
    }
}
