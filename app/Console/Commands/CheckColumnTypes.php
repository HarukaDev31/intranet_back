<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckColumnTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:column-types';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar tipos de datos de columnas';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Verificando Tipos de Datos ===');

        // Verificar productos_importados_excel
        $this->info("\n--- Tabla productos_importados_excel ---");
        $columns = DB::select("SHOW COLUMNS FROM productos_importados_excel");
        foreach ($columns as $column) {
            if (in_array($column->Field, ['entidad_id', 'tipo_etiquetado_id'])) {
                $this->info("  {$column->Field}: {$column->Type} " . ($column->Null === 'YES' ? 'NULL' : 'NOT NULL') . " Default: {$column->Default} Extra: {$column->Extra}");
            }
        }

        // Verificar bd_entidades_reguladoras
        $this->info("\n--- Tabla bd_entidades_reguladoras ---");
        $columns = DB::select("SHOW COLUMNS FROM bd_entidades_reguladoras");
        foreach ($columns as $column) {
            if ($column->Field === 'id') {
                $this->info("  {$column->Field}: {$column->Type} " . ($column->Null === 'YES' ? 'NULL' : 'NOT NULL') . " Default: {$column->Default} Extra: {$column->Extra}");
            }
        }

        // Verificar bd_productos
        $this->info("\n--- Tabla bd_productos ---");
        $columns = DB::select("SHOW COLUMNS FROM bd_productos");
        foreach ($columns as $column) {
            if ($column->Field === 'id') {
                $this->info("  {$column->Field}: {$column->Type} " . ($column->Null === 'YES' ? 'NULL' : 'NOT NULL') . " Default: {$column->Default} Extra: {$column->Extra}");
            }
        }

        // Verificar datos en las tablas
        $this->info("\n--- Verificando datos ---");
        $countEntidades = DB::table('bd_entidades_reguladoras')->count();
        $countProductos = DB::table('bd_productos')->count();
        $countProductosImportados = DB::table('productos_importados_excel')->count();
        
        $this->info("  bd_entidades_reguladoras: {$countEntidades} registros");
        $this->info("  bd_productos: {$countProductos} registros");
        $this->info("  productos_importados_excel: {$countProductosImportados} registros");

        // Verificar valores en entidad_id y tipo_etiquetado_id
        if ($countProductosImportados > 0) {
            $entidadIds = DB::table('productos_importados_excel')->whereNotNull('entidad_id')->pluck('entidad_id')->unique();
            $tipoEtiquetadoIds = DB::table('productos_importados_excel')->whereNotNull('tipo_etiquetado_id')->pluck('tipo_etiquetado_id')->unique();
            
            $this->info("  Valores únicos en entidad_id: " . $entidadIds->implode(', '));
            $this->info("  Valores únicos en tipo_etiquetado_id: " . $tipoEtiquetadoIds->implode(', '));
        }

        return 0;
    }
}
