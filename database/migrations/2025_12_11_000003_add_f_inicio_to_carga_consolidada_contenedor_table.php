<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddFInicioToCargaConsolidadaContenedorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            if (!Schema::hasColumn('carga_consolidada_contenedor', 'f_inicio')) {
                $table->date('f_inicio')->nullable();
            }
        });

        // Permitir operar con fechas "cero"
        DB::statement("SET SESSION sql_mode=''");

        // Backfill: para registros que no tengan f_inicio, construir fecha con año de f_cierre y mes de 'mes', día = 01
        DB::statement(
            "UPDATE carga_consolidada_contenedor SET f_inicio = (" .
            " CASE WHEN (f_inicio IS NULL OR f_inicio = '0000-00-00') THEN (CASE WHEN f_cierre IS NOT NULL AND f_cierre != '0000-00-00' THEN " .
            " STR_TO_DATE(CONCAT(DATE_FORMAT(f_cierre, '%Y'), '-', LPAD(CASE WHEN COALESCE(mes,0) BETWEEN 1 AND 12 THEN mes ELSE DATE_FORMAT(f_cierre, '%m') END,2,'0'), '-01'), '%Y-%m-%d') " .
            " ELSE NULL END) ELSE f_inicio END) " .
            " WHERE f_inicio IS NULL OR f_inicio = '0000-00-00'"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            if (Schema::hasColumn('carga_consolidada_contenedor', 'f_inicio')) {
                $table->dropColumn('f_inicio');
            }
        });
    }
}
