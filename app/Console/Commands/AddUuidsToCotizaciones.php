<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AddUuidsToCotizaciones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cotizaciones:add-uuids {--batch=100 : Número de registros a procesar por lote} {--dry-run : Solo mostrar qué se haría sin ejecutar cambios}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Añadir UUIDs a todas las cotizaciones que no los tienen';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $batchSize = $this->option('batch');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 MODO DRY-RUN: Solo se mostrará qué se haría sin ejecutar cambios');
        }

        $this->info('Iniciando proceso de añadir UUIDs a cotizaciones...');

        try {
            // Verificar si la columna uuid existe
            $columns = DB::select("SHOW COLUMNS FROM contenedor_consolidado_cotizacion LIKE 'uuid'");
            
            if (empty($columns)) {
                $this->error('❌ La columna "uuid" no existe en la tabla contenedor_consolidado_cotizacion');
                $this->info('💡 Ejecuta primero: php artisan migrate');
                return 1;
            }

            // Contar cotizaciones sin UUID (NULL o string vacío)
            $totalSinUuid = DB::table('contenedor_consolidado_cotizacion')
                ->where(function($query) {
                    $query->whereNull('uuid')
                          ->orWhere('uuid', '');
                })
                ->whereNull('deleted_at')
                ->count();

            if ($totalSinUuid === 0) {
                $this->info('✅ Todas las cotizaciones ya tienen UUID asignado');
                return 0;
            }

            $this->info("📊 Se encontraron {$totalSinUuid} cotizaciones sin UUID");

            $procesadas = 0;
            $lote = 1;

            do {
                // Obtener lote de cotizaciones sin UUID (NULL o string vacío)
                $cotizaciones = DB::table('contenedor_consolidado_cotizacion')
                    ->where(function($query) {
                        $query->whereNull('uuid')
                              ->orWhere('uuid', '');
                    })
                    ->whereNull('deleted_at')
                    ->select('id', 'nombre')
                    ->limit($batchSize)
                    ->get();

                if ($cotizaciones->isEmpty()) {
                    break;
                }

                $this->info("🔄 Procesando lote {$lote} ({$cotizaciones->count()} registros)...");

                foreach ($cotizaciones as $cotizacion) {
                    $uuid = Str::uuid()->toString();

                    if ($dryRun) {
                        $this->line("  📝 Se asignaría UUID '{$uuid}' a cotización ID {$cotizacion->id} ({$cotizacion->nombre})");
                    } else {
                        try {
                            DB::table('contenedor_consolidado_cotizacion')
                                ->where('id', $cotizacion->id)
                                ->whereNull('deleted_at')
                                ->update(['uuid' => $uuid]);

                            $procesadas++;
                        } catch (\Exception $e) {
                            $this->error("  ❌ Error actualizando cotización ID {$cotizacion->id}: " . $e->getMessage());
                        }
                    }
                }

                if (!$dryRun && $procesadas > 0 && $procesadas % 50 == 0) {
                    $this->info("  ✅ {$procesadas} cotizaciones procesadas hasta ahora...");
                }

                $lote++;

            } while ($cotizaciones->count() === $batchSize);

            if ($dryRun) {
                $this->info("🔍 DRY-RUN completado. Se procesarían {$totalSinUuid} cotizaciones");
                $this->info("💡 Para ejecutar realmente: php artisan cotizaciones:add-uuids");
            } else {
                $this->info("✅ Proceso completado exitosamente");
                $this->info("📊 Total de cotizaciones procesadas: {$procesadas}");

                // Verificar resultado final
                $restantesSinUuid = DB::table('contenedor_consolidado_cotizacion')
                    ->where(function($query) {
                        $query->whereNull('uuid')
                              ->orWhere('uuid', '');
                    })
                    ->whereNull('deleted_at')
                    ->count();

                if ($restantesSinUuid === 0) {
                    $this->info("🎉 ¡Todas las cotizaciones ahora tienen UUID asignado!");
                } else {
                    $this->warn("⚠️  Aún quedan {$restantesSinUuid} cotizaciones sin UUID");
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error durante el proceso: ' . $e->getMessage());
            return 1;
        }
    }
}