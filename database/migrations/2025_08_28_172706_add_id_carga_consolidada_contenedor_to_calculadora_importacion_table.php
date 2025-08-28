<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdCargaConsolidadaContenedorToCalculadoraImportacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //if column id_carga_consolidada_contenedor exists, drop it
        if (Schema::hasColumn('calculadora_importacion', 'id_carga_consolidada_contenedor')) {
            Schema::table('calculadora_importacion', function (Blueprint $table) {
                $table->dropColumn('id_carga_consolidada_contenedor');
            });
        }
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->unsignedInteger('id_carga_consolidada_contenedor')->nullable()->after('estado');
            $table->foreign('id_carga_consolidada_contenedor', 'fk_calc_imp_contenedor')
                  ->references('id')
                  ->on('carga_consolidada_contenedor')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->dropForeign('fk_calc_imp_contenedor');
            $table->dropColumn('id_carga_consolidada_contenedor');
        });
    }
}
