<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddForeignKeysSqlToProductosImportadosExcelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Agregar foreign key para entidad_id usando SQL directo
      
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar foreign keys
        try {
            DB::statement('ALTER TABLE productos_importados_excel DROP FOREIGN KEY fk_productos_entidad');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        try {
            DB::statement('ALTER TABLE productos_importados_excel DROP FOREIGN KEY fk_productos_tipo_etiquetado');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
    }
}
