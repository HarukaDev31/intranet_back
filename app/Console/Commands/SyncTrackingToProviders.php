<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncTrackingToProviders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracking:sync-proveedores-cobrando {--force : Actually apply changes} {--limit=0 : Limit number of providers to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync providers with estados=COBRANDO into contenedor_proveedor_estados_tracking using the correct pattern';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $force = $this->option('force');
        $limit = (int)$this->option('limit');

        $this->info('Searching providers with estados = "COBRANDO"...');

        $query = DB::table('contenedor_consolidado_cotizacion_proveedores')
            ->where(function($q) {
                $q->where('estados', 'COBRANDO')
                  ->orWhereNull('estados')
                  ->orWhere('estados', '');
            })
            ->whereExists(function($sub) {
                $sub->select(DB::raw(1))
                    ->from('contenedor_consolidado_cotizacion as c')
                    ->whereRaw('c.id = contenedor_consolidado_cotizacion_proveedores.id_cotizacion')
                    ->where('c.estado_cotizador', 'CONFIRMADO');
            })
            ->select('id', 'id_cotizacion', 'estados');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $providers = $query->get();

        $total = $providers->count();
        if ($total === 0) {
            $this->info('No providers found with estados = COBRANDO.');
            return 0;
        }

        $this->info("Found {$total} providers.");

        $processed = 0;
        foreach ($providers as $prov) {
            $processed++;
            $this->line("[{$processed}/{$total}] Provider id={$prov->id} cotizacion={$prov->id_cotizacion}");

            $currentState = isset($prov->estados) && $prov->estados !== '' ? $prov->estados : null;

            // New logic per user: if payments exist for the cotizacion -> set RESERVADO.
            // Otherwise, DO NOT consult tracking here (you will run tracking sync later) and set NULL.
            $replacement = null;

            // Check payments for this cotizacion (LOGISTICA or IMPUESTOS)
            $hasPayments = DB::table('contenedor_consolidado_cotizacion_coordinacion_pagos as p')
                ->join('cotizacion_coordinacion_pagos_concept as c', 'p.id_concept', '=', 'c.id')
                ->where('p.id_cotizacion', $prov->id_cotizacion)
                ->whereIn('c.name', ['LOGISTICA', 'IMPUESTOS'])
                ->exists();

            if ($hasPayments) {
                $replacement = 'RESERVADO';
            } else {
                $replacement = null; // normalize to NULL; tracking will be handled later
            }

                $displayCurrent = $currentState === null ? 'NULL' : $currentState;
                $displayReplacement = $replacement === null ? 'NULL' : $replacement;

                if (! $force) {
                    $this->line('  - Dry run: would set provider.estados FROM ' . $displayCurrent . ' TO ' . $displayReplacement);
                    continue;
                }

                try {
                    DB::beginTransaction();

                    // Persist only provider update now (user will run tracking command separately)
                    DB::table('contenedor_consolidado_cotizacion_proveedores')
                        ->where('id', $prov->id)
                        ->update(['estados' => $replacement]);

                    DB::commit();
                    $this->line('  - Applied: provider.estados FROM ' . $displayCurrent . ' TO ' . $displayReplacement);
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error('  - Error applying changes: ' . $e->getMessage());
                }
        }

        $this->info('Done. Processed: ' . $processed);
        if (! $force) {
            $this->info('Note: No changes applied. Rerun with --force to apply.');
        }

        return 0;
    }
}
