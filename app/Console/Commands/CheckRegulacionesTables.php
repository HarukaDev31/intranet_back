<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckRegulacionesTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:regulaciones-tables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar las tablas de regulaciones';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Verificando Tablas de Regulaciones ===');

        $tables = [
            'bd_entidades_reguladoras',
            'bd_productos',
            'bd_productos_regulaciones_antidumping',
            'bd_productos_regulaciones_antidumping_media',
            'bd_productos_regulaciones_permiso',
            'bd_productos_regulaciones_permiso_media',
            'bd_productos_regulaciones_etiquetado',
            'bd_productos_regulaciones_etiquetado_media',
            'bd_productos_regulaciones_documentos_especiales',
            'bd_productos_regulaciones_documentos_especiales_media'
        ];

        foreach ($tables as $table) {
            try {
                $exists = DB::select("SHOW TABLES LIKE '{$table}'");
                if (!empty($exists)) {
                    $this->info("✓ Tabla {$table} existe");
                    
                    // Verificar estructura
                    $columns = DB::select("DESCRIBE {$table}");
                    $this->line("  Columnas: " . count($columns));
                    
                    // Verificar foreign keys
                    $foreignKeys = DB::select("
                        SELECT 
                            COLUMN_NAME,
                            REFERENCED_TABLE_NAME,
                            REFERENCED_COLUMN_NAME
                        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = '{$table}' 
                        AND REFERENCED_TABLE_NAME IS NOT NULL
                    ");
                    
                    if (!empty($foreignKeys)) {
                        $this->line("  Foreign Keys: " . count($foreignKeys));
                        foreach ($foreignKeys as $fk) {
                            $this->line("    - {$fk->COLUMN_NAME} -> {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}");
                        }
                    }
                } else {
                    $this->error("✗ Tabla {$table} NO existe");
                }
            } catch (\Exception $e) {
                $this->error("✗ Error verificando tabla {$table}: " . $e->getMessage());
            }
        }

        // Verificar foreign keys en productos_importados_excel
        $this->info("\n--- Verificando Foreign Keys en productos_importados_excel ---");
        try {
            $foreignKeys = DB::select("
                SELECT 
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'productos_importados_excel' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            
            if (!empty($foreignKeys)) {
                $this->info("Foreign Keys encontradas:");
                foreach ($foreignKeys as $fk) {
                    $this->line("  - {$fk->COLUMN_NAME} -> {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}");
                }
            } else {
                $this->warn("No se encontraron foreign keys en productos_importados_excel");
            }
        } catch (\Exception $e) {
            $this->error("Error verificando foreign keys: " . $e->getMessage());
        }

        $this->info("\n=== Verificación completada ===");
        return 0;
    }
}
