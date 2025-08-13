<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFileExtToContenedorConsolidadoAlmacenInspectionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contenedor_consolidado_almacen_inspection', function (Blueprint $table) {
            $table->string('file_ext', 10)->nullable()->after('file_type')->comment('Extensión del archivo');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contenedor_consolidado_almacen_inspection', function (Blueprint $table) {
            $table->dropColumn('file_ext');
        });
    }
}
