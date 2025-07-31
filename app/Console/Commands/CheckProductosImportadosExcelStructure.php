<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckProductosImportadosExcelStructure extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:productos-importados-excel-structure';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar la estructura completa de la tabla productos_importados_excel';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Verificando Estructura de productos_importados_excel ===');

        // Verificar que la tabla existe
        $exists = DB::select("SHOW TABLES LIKE 'productos_importados_excel'");
        if (empty($exists)) {
            $this->error('✗ La tabla productos_importados_excel NO existe');
            return 1;
        }

        $this->info('✓ La tabla productos_importados_excel existe');

        // Obtener estructura completa
        $columns = DB::select("SHOW COLUMNS FROM productos_importados_excel");
        $this->info("\n--- Estructura de Columnas ---");
        $this->info(sprintf("%-20s %-15s %-8s %-10s %-10s", 'Campo', 'Tipo', 'Null', 'Default', 'Extra'));
        $this->info(str_repeat('-', 70));
        
        foreach ($columns as $column) {
            $this->info(sprintf("%-20s %-15s %-8s %-10s %-10s", 
                $column->Field, 
                $column->Type, 
                $column->Null, 
                $column->Default ?? 'NULL', 
                $column->Extra ?? ''
            ));
        }

        // Verificar índices
        $indexes = DB::select("SHOW INDEX FROM productos_importados_excel");
        $this->info("\n--- Índices ---");
        foreach ($indexes as $index) {
            if ($index->Key_name === 'PRIMARY') {
                $this->info("  PRIMARY KEY: {$index->Column_name}");
            } else {
                $this->info("  INDEX {$index->Key_name}: {$index->Column_name}");
            }
        }

        // Verificar foreign keys
        $foreignKeys = DB::select("
            SELECT 
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME,
                CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'productos_importados_excel' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        
        $this->info("\n--- Foreign Keys ---");
        if (!empty($foreignKeys)) {
            foreach ($foreignKeys as $fk) {
                $this->info("  ✓ {$fk->CONSTRAINT_NAME}: {$fk->COLUMN_NAME} → {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}");
            }
        } else {
            $this->warn("  ✗ No se encontraron foreign keys");
        }

        // Verificar configuración de la tabla
        $tableInfo = DB::select("SHOW TABLE STATUS LIKE 'productos_importados_excel'");
        if (!empty($tableInfo)) {
            $info = $tableInfo[0];
            $this->info("\n--- Configuración de la Tabla ---");
            $this->info("  Engine: {$info->Engine}");
            $this->info("  Collation: {$info->Collation}");
            $this->info("  Auto Increment: {$info->Auto_increment}");
            $this->info("  Rows: {$info->Rows}");
        }

        // Verificar datos
        $count = DB::table('productos_importados_excel')->count();
        $this->info("\n--- Datos ---");
        $this->info("  Total de registros: {$count}");

        $this->info("\n=== Verificación completada ===");
        return 0;
    }
}
