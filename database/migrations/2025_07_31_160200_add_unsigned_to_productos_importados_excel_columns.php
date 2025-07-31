<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddUnsignedToProductosImportadosExcelColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Agregar unsigned a entidad_id
        DB::statement('ALTER TABLE productos_importados_excel MODIFY entidad_id int unsigned NULL');
        
        // Agregar unsigned a tipo_etiquetado_id
        DB::statement('ALTER TABLE productos_importados_excel MODIFY tipo_etiquetado_id int unsigned NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revertir unsigned de entidad_id
        DB::statement('ALTER TABLE productos_importados_excel MODIFY entidad_id int NULL');
        
        // Revertir unsigned de tipo_etiquetado_id
        DB::statement('ALTER TABLE productos_importados_excel MODIFY tipo_etiquetado_id int NULL');
    }
}
