<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixTrackingEstadosNulos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracking:fix-estados-nulos {--dry-run : Ejecutar sin hacer cambios reales}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige registros de tracking con estados null o vacíos inferiendo el estado correcto del proveedor';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 Modo DRY-RUN: No se harán cambios en la base de datos');
            $this->newLine();
        }

        $this->info('Iniciando corrección de estados nulos en tracking...');
        $this->newLine();

        DB::beginTransaction();

        try {
            // Obtener todos los registros con estado null o vacío
            // Nota: en la tabla `contenedor_proveedor_estados_tracking` la columna se llama `estado` (singular)
            $trackingNulos = DB::table('contenedor_proveedor_estados_tracking')
                ->whereNull('estado')
                ->orWhere('estado', '')
                ->orderBy('id_proveedor')
                ->orderBy('created_at')
                ->get();

            $totalNulos = $trackingNulos->count();
            $this->info("📊 Total de registros con estados nulos: {$totalNulos}");
            $this->newLine();

            if ($totalNulos === 0) {
                $this->info('✅ No hay registros con estados nulos. Todo está correcto.');
                DB::commit();
                return Command::SUCCESS;
            }

            $corregidos = 0;
            $sinSolucion = 0;
            $problemasPorProveedor = [];

            // Procesar cada registro nulo
            foreach ($trackingNulos as $trackingNulo) {
                $this->line("Procesando tracking ID: {$trackingNulo->id} (Proveedor: {$trackingNulo->id_proveedor})");

                // Estrategia única: Obtener del estado actual del proveedor
                $proveedorActual = DB::table('contenedor_consolidado_cotizacion_proveedores')
                    ->where('id', $trackingNulo->id_proveedor)
                    ->first();

                if ($proveedorActual && !empty($proveedorActual->estados)) {
                    $estadoInferido = $proveedorActual->estados;
                    
                    $this->line("  ├─ Estado inferido del proveedor actual: {$estadoInferido}");

                    // Verificar que la cotización exista
                    $cotizacionExists = DB::table('contenedor_consolidado_cotizacion')
                        ->where('id', $trackingNulo->id_cotizacion)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (!$cotizacionExists) {
                        $this->warn("  └─ ⛔ Cotización {$trackingNulo->id_cotizacion} no encontrada en contenedor_consolidado_cotizacion, se omite la actualización.");
                        $sinSolucion++;
                        $problemasPorProveedor[] = [
                            'tracking_id' => $trackingNulo->id,
                            'proveedor_id' => $trackingNulo->id_proveedor,
                            'cotizacion_id' => $trackingNulo->id_cotizacion,
                            'created_at' => $trackingNulo->created_at,
                            'motivo' => 'cotizacion_no_existente'
                        ];
                        continue;
                    }

                    // Validar que la cotización asociada esté en estado 'CONFIRMADO'
                    $estadoCotizador = DB::table('contenedor_consolidado_cotizacion')
                        ->where('id', $trackingNulo->id_cotizacion)
                        ->whereNull('deleted_at')
                        ->value('estado_cotizador');

                    if ($estadoCotizador !== 'CONFIRMADO') {
                        $this->warn("  └─ ⛔ Cotización {$trackingNulo->id_cotizacion} no está CONFIRMADO ({$estadoCotizador}), se omite la actualización.");
                        // Contabilizar como sin solución automática para revisión manual
                        $sinSolucion++;
                        $problemasPorProveedor[] = [
                            'tracking_id' => $trackingNulo->id,
                            'proveedor_id' => $trackingNulo->id_proveedor,
                            'cotizacion_id' => $trackingNulo->id_cotizacion,
                            'created_at' => $trackingNulo->created_at,
                            'motivo' => 'cotizacion_no_confirmada',
                            'estado_cotizador' => $estadoCotizador
                        ];
                        continue;
                    }

                    if (!$dryRun) {
                        // Actualizar la columna `estado` en la tabla de tracking (singular)
                        DB::table('contenedor_proveedor_estados_tracking')
                            ->where('id', $trackingNulo->id)
                            ->update(['estado' => $estadoInferido]);
                    }

                    $corregidos++;
                    $this->info("  └─ ✅ Corregido");
                    continue;
                }

                // No se pudo inferir el estado
                $sinSolucion++;
                $problemasPorProveedor[] = [
                    'tracking_id' => $trackingNulo->id,
                    'proveedor_id' => $trackingNulo->id_proveedor,
                    'cotizacion_id' => $trackingNulo->id_cotizacion,
                    'created_at' => $trackingNulo->created_at
                ];
                $this->warn("  └─ ⚠️  No se pudo inferir el estado (proveedor no encontrado o sin estado)");
            }

            $this->newLine();
            $this->info('📈 Resumen de la corrección:');
            $this->table(
                ['Métrica', 'Cantidad'],
                [
                    ['Total registros nulos', $totalNulos],
                    ['Corregidos exitosamente', $corregidos],
                    ['Sin solución', $sinSolucion],
                ]
            );

            if ($sinSolucion > 0) {
                $this->newLine();
                $this->warn("⚠️  Registros sin solución automática:");
                $this->table(
                    ['Tracking ID', 'Proveedor ID', 'Cotización ID', 'Fecha Creación'],
                    array_map(function ($problema) {
                        return [
                            $problema['tracking_id'],
                            $problema['proveedor_id'],
                            $problema['cotizacion_id'],
                            $problema['created_at']
                        ];
                    }, $problemasPorProveedor)
                );
                $this->newLine();
                $this->warn('Estos registros deben ser revisados manualmente.');
            }

            if ($dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->info('🔍 DRY-RUN completado. No se realizaron cambios.');
                $this->info('Ejecuta sin --dry-run para aplicar los cambios.');
            } else {
                DB::commit();
                $this->newLine();
                $this->info('✅ Corrección completada exitosamente.');
                
                Log::info('Comando FixTrackingEstadosNulos ejecutado', [
                    'total_nulos' => $totalNulos,
                    'corregidos' => $corregidos,
                    'sin_solucion' => $sinSolucion,
                    'problemas' => $problemasPorProveedor
                ]);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error durante la corrección: ' . $e->getMessage());
            Log::error('Error en FixTrackingEstadosNulos: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
