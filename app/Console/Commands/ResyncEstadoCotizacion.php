<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\CargaConsolidada\Cotizacion;
use App\Http\Controllers\CargaConsolidada\PagosController;

class ResyncEstadoCotizacion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * --force    Force update even if estado is COBRANDO
     * --chunk=   How many records to process per chunk (default 500)
     * --only-confirmed  Only process cotizaciones with estado_cotizador = 'CONFIRMADO'
     * --dry-run  Do not persist changes, just report
     */
    protected $signature = 'cotizacion:resync-estados {--force} {--chunk=500} {--only-confirmed} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula y actualiza estado_cotizacion_final para todas las cotizaciones a partir de sus pagos (LOGISTICA/IMPUESTOS).';

    public function handle()
    {
        $force = $this->option('force') ? true : false;
        $dryRun = $this->option('dry-run') ? true : false;
        $chunk = (int) $this->option('chunk') ?: 500;
        $onlyConfirmed = $this->option('only-confirmed') ? true : false;

        $this->info("Starting resync of estado_cotizacion_final (chunk={$chunk})");
        if ($force) $this->info('Force mode: will overwrite COBRANDO states.');
        if ($dryRun) $this->info('Dry run: no changes will be persisted.');
        if ($onlyConfirmed) $this->info("Filtering: only cotizaciones with estado_cotizador='CONFIRMADO'.");

        $query = Cotizacion::query();
        // avoid importacion clients
        $query->whereNull('id_cliente_importacion');
        if ($onlyConfirmed) {
            $query->where('estado_cotizador', 'CONFIRMADO');
        }

        $total = $query->count();
        if ($total === 0) {
            $this->info('No cotizaciones found for processing.');
            return 0;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        $pagosController = app(PagosController::class);

        $query->orderBy('id')->chunkById($chunk, function ($items) use (&$bar, &$updated, &$skipped, &$errors, $pagosController, $force, $dryRun) {
            foreach ($items as $cot) {
                try {
                    // capture previous estado before sync (controller may persist changes)
                    $prevEstado = $cot->estado_cotizacion_final;
                    // ensure contenedor relation loaded to get carga
                    if (! isset($cot->contenedor)) {
                        $cot->load('contenedor');
                    }
                    $res = $pagosController->syncEstadoCotizacionFromPayments($cot->id, $force);
                    if (isset($res['success']) && $res['success'] === true) {
                        if (!empty($res['skipped'])) {
                            $skipped++;
                            $result = 'skipped';
                        } elseif (!empty($res['updated'])) {
                            // if dry-run, rollback the change made inside the controller
                            if ($dryRun) {
                                // Controller already persisted change; attempt to restore previous value if provided
                                if (isset($res['previous'])) {
                                    // restore value
                                    Cotizacion::where('id', $cot->id)->update(['estado_cotizacion_final' => $res['previous']]);
                                } else {
                                    // restore to captured prevEstado
                                    Cotizacion::where('id', $cot->id)->update(['estado_cotizacion_final' => $prevEstado]);
                                }
                            }
                            $updated++;
                            $result = 'updated';
                        } else {
                            $result = 'no_change';
                        }
                        // CSV generation disabled
                    } else {
                        $errors++;
                        Log::warning('ResyncEstadoCotizacion: unexpected result', ['id' => $cot->id, 'res' => $res]);
                        // CSV generation disabled
                    }
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('ResyncEstadoCotizacion error processing cotizacion', ['id' => $cot->id, 'error' => $e->getMessage()]);
                    // CSV generation disabled
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        // CSV generation disabled (removed as requested)
        $this->info("Done. Processed: {$total}. Updated: {$updated}. Skipped: {$skipped}. Errors: {$errors}.");

        return 0;
    }
}
