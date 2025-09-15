<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeColumnsNullableInCargaConsolidadaContenedorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            // Hacer las columnas nullable
            $table->string('naviera')->nullable()->change();
            $table->decimal('multa', 10, 2)->nullable()->change();
            $table->text('observaciones')->nullable()->change();
            $table->string('tipo_contenedor')->nullable()->change();
            $table->date('fecha_levante')->nullable()->change();
            $table->date('fecha_zarpe')->nullable()->change();
            $table->string('numero_dua')->nullable()->change();
            $table->date('fecha_arribo')->nullable()->change();
            $table->decimal('valor_fob', 10, 2)->nullable()->change();
            $table->date('fecha_declaracion')->nullable()->change();
            $table->decimal('valor_flete', 10, 2)->nullable()->change();
            $table->string('canal_control')->nullable()->change();
            $table->decimal('costo_destino', 10, 2)->nullable()->change();
            $table->decimal('ajuste_valor', 10, 2)->nullable()->change();
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
