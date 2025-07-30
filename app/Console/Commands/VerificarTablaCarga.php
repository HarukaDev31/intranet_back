<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\BaseDatos\CargaConsolidadaContenedor;
use App\Models\BaseDatos\ProductoImportadoExcel;

class VerificarTablaCarga extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verificar:tabla-carga';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica la estructura de la tabla carga_consolidada_contenedor';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Verificación de Tabla Carga Consolidada Contenedor ===');
        $this->line('');

        try {
            // 1. Verificar si la tabla existe
            $this->info('1. Verificando existencia de la tabla...');
            $tableExists = DB::getSchemaBuilder()->hasTable('carga_consolidada_contenedor');
            
            if ($tableExists) {
                $this->line('   ✅ Tabla carga_consolidada_contenedor existe');
            } else {
                $this->error('   ❌ Tabla carga_consolidada_contenedor NO existe');
                return 1;
            }
            
            $this->line('');

            // 2. Verificar estructura de la tabla
            $this->info('2. Verificando estructura de la tabla...');
            $columns = DB::select("DESCRIBE carga_consolidada_contenedor");
            
            $this->line('   Columnas encontradas:');
            foreach ($columns as $column) {
                $this->line("   - {$column->Field} ({$column->Type}) - Key: {$column->Key}");
            }
            
            $this->line('');

            // 3. Verificar datos en la tabla
            $this->info('3. Verificando datos en la tabla...');
            $totalRecords = DB::table('carga_consolidada_contenedor')->count();
            $this->line("   ✅ Total de registros: {$totalRecords}");
            
            if ($totalRecords > 0) {
                $sampleRecords = DB::table('carga_consolidada_contenedor')->limit(5)->get();
                $this->line('   Muestra de registros:');
                foreach ($sampleRecords as $record) {
                    $this->line("   - ID: {$record->id}, Carga: {$record->carga}");
                }
            }
            
            $this->line('');

            // 4. Verificar relación con productos
            $this->info('4. Verificando relación con productos...');
            $productosConContenedor = ProductoImportadoExcel::whereNotNull('idContenedor')->count();
            $this->line("   ✅ Productos con contenedor: {$productosConContenedor}");
            
            $contenedoresUnicos = ProductoImportadoExcel::select('idContenedor')
                ->whereNotNull('idContenedor')
                ->distinct()
                ->pluck('idContenedor')
                ->toArray();
            
            $this->line("   ✅ Contenedores únicos en productos: " . count($contenedoresUnicos));
            if (count($contenedoresUnicos) > 0) {
                $this->line("   - Contenedores: " . implode(', ', $contenedoresUnicos));
            }
            
            $this->line('');

            // 5. Verificar si los contenedores de productos existen en carga_consolidada_contenedor
            $this->info('5. Verificando correspondencia de contenedores...');
            foreach ($contenedoresUnicos as $contenedorId) {
                $existeEnCarga = DB::table('carga_consolidada_contenedor')
                    ->where('id', $contenedorId)
                    ->exists();
                
                if ($existeEnCarga) {
                    $this->line("   ✅ Contenedor {$contenedorId} existe en carga_consolidada_contenedor");
                } else {
                    $this->warn("   ⚠️  Contenedor {$contenedorId} NO existe en carga_consolidada_contenedor");
                }
            }
            
            $this->line('');

            // 6. Probar la relación
            $this->info('6. Probando relación...');
            try {
                $productoConRelacion = ProductoImportadoExcel::with('contenedor')->first();
                if ($productoConRelacion && $productoConRelacion->contenedor) {
                    $this->line("   ✅ Relación funciona correctamente");
                    $this->line("   - Producto ID: {$productoConRelacion->id}");
                    $this->line("   - Contenedor ID: {$productoConRelacion->idContenedor}");
                    $this->line("   - Carga: {$productoConRelacion->contenedor->carga}");
                } else {
                    $this->warn("   ⚠️  No se encontró producto con relación válida");
                }
            } catch (\Exception $e) {
                $this->error("   ❌ Error en la relación: " . $e->getMessage());
            }

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
} 