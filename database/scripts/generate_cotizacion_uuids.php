<?php

/**
 * Script para generar UUIDs para las cotizaciones existentes
 * 
 * Este script puede ejecutarse independientemente para generar UUIDs
 * para todos los registros de la tabla contenedor_consolidado_cotizacion
 * que no tengan UUID asignado.
 * 
 * Uso: php artisan tinker --execute="require_once 'database/scripts/generate_cotizacion_uuids.php';"
 * O desde un comando personalizado de Artisan
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class GenerateCotizacionUuids
{
    /**
     * Ejecutar la generación de UUIDs
     */
    public static function run()
    {
        echo "🚀 Iniciando generación de UUIDs para cotizaciones...\n";
        
        try {
            // Verificar si la columna UUID existe
            if (!self::columnExists()) {
                echo "❌ Error: La columna 'uuid' no existe en la tabla 'contenedor_consolidado_cotizacion'\n";
                echo "   Ejecuta primero la migración: php artisan migrate\n";
                return false;
            }

            // Obtener registros sin UUID
            $cotizacionesSinUuid = DB::table('contenedor_consolidado_cotizacion')
                ->whereNull('uuid')
                ->select('id')
                ->get();

            $total = $cotizacionesSinUuid->count();
            
            if ($total === 0) {
                echo "✅ Todas las cotizaciones ya tienen UUID asignado.\n";
                return true;
            }

            echo "📊 Encontradas {$total} cotizaciones sin UUID\n";
            echo "🔄 Generando UUIDs...\n";

            $processed = 0;
            $batchSize = 100;

            // Procesar en lotes para mejor rendimiento
            foreach ($cotizacionesSinUuid->chunk($batchSize) as $batch) {
                foreach ($batch as $cotizacion) {
                    $uuid = Str::uuid();
                    
                    DB::table('contenedor_consolidado_cotizacion')
                        ->where('id', $cotizacion->id)
                        ->update(['uuid' => $uuid]);
                    
                    $processed++;
                    
                    // Mostrar progreso cada 50 registros
                    if ($processed % 50 === 0) {
                        $percentage = round(($processed / $total) * 100, 1);
                        echo "   Procesados: {$processed}/{$total} ({$percentage}%)\n";
                    }
                }
            }

            echo "✅ Proceso completado exitosamente!\n";
            echo "📈 Total de UUIDs generados: {$processed}\n";
            
            // Log del proceso
            Log::info("Script de generación de UUIDs completado", [
                'total_processed' => $processed,
                'execution_time' => microtime(true) - LARAVEL_START
            ]);

            return true;

        } catch (\Exception $e) {
            echo "❌ Error durante la generación de UUIDs: " . $e->getMessage() . "\n";
            Log::error("Error en script de generación de UUIDs", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Verificar si la columna UUID existe
     */
    private static function columnExists()
    {
        try {
            $columns = DB::getSchemaBuilder()->getColumnListing('contenedor_consolidado_cotizacion');
            return in_array('uuid', $columns);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verificar la integridad de los UUIDs generados
     */
    public static function verifyUuids()
    {
        echo "🔍 Verificando integridad de UUIDs...\n";

        // Contar registros sin UUID
        $sinUuid = DB::table('contenedor_consolidado_cotizacion')
            ->whereNull('uuid')
            ->count();

        // Contar registros con UUID
        $conUuid = DB::table('contenedor_consolidado_cotizacion')
            ->whereNotNull('uuid')
            ->count();

        // Verificar duplicados
        $duplicados = DB::table('contenedor_consolidado_cotizacion')
            ->select('uuid')
            ->whereNotNull('uuid')
            ->groupBy('uuid')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        echo "📊 Resultados de verificación:\n";
        echo "   - Registros con UUID: {$conUuid}\n";
        echo "   - Registros sin UUID: {$sinUuid}\n";
        echo "   - UUIDs duplicados: {$duplicados}\n";

        if ($sinUuid === 0 && $duplicados === 0) {
            echo "✅ Todos los registros tienen UUIDs únicos correctamente asignados\n";
            return true;
        } else {
            echo "⚠️  Se encontraron problemas en la verificación\n";
            return false;
        }
    }
}

// Ejecutar el script si se llama directamente
if (isset($argv) && basename($argv[0]) === basename(__FILE__)) {
    GenerateCotizacionUuids::run();
}
