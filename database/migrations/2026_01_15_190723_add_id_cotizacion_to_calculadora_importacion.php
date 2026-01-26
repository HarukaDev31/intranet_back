<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdCotizacionToCalculadoraImportacion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->integer('id_cotizacion')->nullable()->after('cod_cotizacion');
            $table->foreign('id_cotizacion')
                  ->references('id')
                  ->on('contenedor_consolidado_cotizacion')
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
            $table->dropForeign(['id_cotizacion']);
            $table->dropColumn('id_cotizacion');
        });
    }
}
