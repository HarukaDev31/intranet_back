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
        DB::statement('ALTER TABLE productos_importados_excel ADD CONSTRAINT fk_productos_entidad FOREIGN KEY (entidad_id) REFERENCES bd_entidades_reguladoras(id) ON DELETE SET NULL ON UPDATE CASCADE');
        
        // Agregar foreign key para tipo_etiquetado_id usando SQL directo
        DB::statement('ALTER TABLE productos_importados_excel ADD CONSTRAINT fk_productos_tipo_etiquetado FOREIGN KEY (tipo_etiquetado_id) REFERENCES bd_productos(id) ON DELETE SET NULL ON UPDATE CASCADE');
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
