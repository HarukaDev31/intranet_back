<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixColumnTypesInProductosImportadosExcelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('productos_importados_excel', function (Blueprint $table) {
            // Cambiar entidad_id de bigint unsigned a int
            $table->integer('entidad_id')->unsigned()->nullable()->change();
            
            // Cambiar tipo_etiquetado_id de bigint unsigned a int
            $table->integer('tipo_etiquetado_id')->unsigned()->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('productos_importados_excel', function (Blueprint $table) {
            // Revertir entidad_id a bigint unsigned
            $table->bigInteger('entidad_id')->unsigned()->nullable()->change();
            
            // Revertir tipo_etiquetado_id a bigint unsigned
            $table->bigInteger('tipo_etiquetado_id')->unsigned()->nullable()->change();
        });
    }
}
