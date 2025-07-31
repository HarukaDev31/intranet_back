<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestForeignKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:foreign-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar las foreign keys manualmente';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Probando Foreign Keys ===');

        // Probar agregar foreign key para entidad_id
        $this->info("\n--- Probando foreign key para entidad_id ---");
        try {
            DB::statement('ALTER TABLE productos_importados_excel ADD CONSTRAINT fk_productos_entidad_test FOREIGN KEY (entidad_id) REFERENCES bd_entidades_reguladoras(id) ON DELETE SET NULL ON UPDATE CASCADE');
            $this->info('✓ Foreign key para entidad_id agregada exitosamente');
            
            // Eliminar la foreign key de prueba
            DB::statement('ALTER TABLE productos_importados_excel DROP FOREIGN KEY fk_productos_entidad_test');
            $this->info('✓ Foreign key de prueba eliminada');
        } catch (\Exception $e) {
            $this->error('✗ Error al agregar foreign key para entidad_id: ' . $e->getMessage());
        }

        // Probar agregar foreign key para tipo_etiquetado_id
        $this->info("\n--- Probando foreign key para tipo_etiquetado_id ---");
        try {
            DB::statement('ALTER TABLE productos_importados_excel ADD CONSTRAINT fk_productos_tipo_etiquetado_test FOREIGN KEY (tipo_etiquetado_id) REFERENCES bd_productos(id) ON DELETE SET NULL ON UPDATE CASCADE');
            $this->info('✓ Foreign key para tipo_etiquetado_id agregada exitosamente');
            
            // Eliminar la foreign key de prueba
            DB::statement('ALTER TABLE productos_importados_excel DROP FOREIGN KEY fk_productos_tipo_etiquetado_test');
            $this->info('✓ Foreign key de prueba eliminada');
        } catch (\Exception $e) {
            $this->error('✗ Error al agregar foreign key para tipo_etiquetado_id: ' . $e->getMessage());
        }

        return 0;
    }
}
