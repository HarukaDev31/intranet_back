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
        DB::statement('ALTER TABLE productos_importados_excel DROP FOREIGN KEY IF EXISTS fk_productos_entidad');
        DB::statement('ALTER TABLE productos_importados_excel DROP FOREIGN KEY IF EXISTS fk_productos_tipo_etiquetado');
    }
}
