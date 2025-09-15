<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class MakeColumnsNullableInCargaConsolidadaContenedorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Primero actualizar los valores '0000-00-00' a NULL
        DB::statement("SET SESSION sql_mode=''");
        DB::statement("UPDATE carga_consolidada_contenedor SET fecha_levante = NULL WHERE fecha_levante = '0000-00-00' OR fecha_levante IS NULL OR fecha_levante < '1000-01-01'");
        DB::statement("UPDATE carga_consolidada_contenedor SET fecha_zarpe = NULL WHERE fecha_zarpe = '0000-00-00' OR fecha_zarpe IS NULL OR fecha_zarpe < '1000-01-01'");
        DB::statement("UPDATE carga_consolidada_contenedor SET fecha_arribo = NULL WHERE fecha_arribo = '0000-00-00' OR fecha_arribo IS NULL OR fecha_arribo < '1000-01-01'");
        DB::statement("UPDATE carga_consolidada_contenedor SET fecha_declaracion = NULL WHERE fecha_declaracion = '0000-00-00' OR fecha_declaracion IS NULL OR fecha_declaracion < '1000-01-01'");

        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            // Hacer las columnas nullable
            $table->string('naviera')->default('')->nullable()->change();
            $table->decimal('multa', 10, 2)->default(0.00)->nullable()->change();
            $table->text('observaciones')->nullable()->change();
            $table->string('tipo_contenedor')->default('')->nullable()->change();
            $table->date('fecha_levante')->nullable()->change();
            $table->date('fecha_zarpe')->nullable()->change();
            $table->string('numero_dua')->default('')->nullable()->change();
            $table->date('fecha_arribo')->nullable()->change();
            $table->decimal('valor_fob', 10, 2)->default(0.00)->nullable()->change();
            $table->date('fecha_declaracion')->nullable()->change();
            $table->decimal('valor_flete', 10, 2)->default(0.00)->nullable()->change();
            $table->string('canal_control')->default('')->nullable()->change();
            $table->decimal('costo_destino', 10, 2)->default(0.00)->nullable()->change();
            $table->decimal('ajuste_valor', 10, 2)->default(0.00)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            // Revertir las columnas a not nullable
            $table->string('naviera')->nullable(false)->change();
            $table->decimal('multa', 10, 2)->nullable(false)->change();
            $table->text('observaciones')->nullable(false)->change();
            $table->string('tipo_contenedor')->nullable(false)->change();
            $table->date('fecha_levante')->nullable(false)->change();
            $table->date('fecha_zarpe')->nullable(false)->change();
            $table->string('numero_dua')->nullable(false)->change();
            $table->date('fecha_arribo')->nullable(false)->change();
            $table->decimal('valor_fob', 10, 2)->nullable(false)->change();
            $table->date('fecha_declaracion')->nullable(false)->change();
            $table->decimal('valor_flete', 10, 2)->nullable(false)->change();
            $table->string('canal_control')->nullable(false)->change();
            $table->decimal('costo_destino', 10, 2)->nullable(false)->change();
            $table->decimal('ajuste_valor', 10, 2)->nullable(false)->change();
        });
    }
}
