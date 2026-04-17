<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * productos puede ser una lista larga (varios ítems separados por comas).
 * VARCHAR(255) provoca SQLSTATE[22001] Data too long for column 'productos'.
 */
class ChangeProductosToTextInConsolidadoDeliveryFormTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('consolidado_delivery_form_province') && Schema::hasColumn('consolidado_delivery_form_province', 'productos')) {
            Schema::table('consolidado_delivery_form_province', function (Blueprint $table) {
                $table->text('productos')->nullable()->change();
            });
        }

        if (Schema::hasTable('consolidado_delivery_form_lima') && Schema::hasColumn('consolidado_delivery_form_lima', 'productos')) {
            Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
                $table->text('productos')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('consolidado_delivery_form_province') && Schema::hasColumn('consolidado_delivery_form_province', 'productos')) {
            Schema::table('consolidado_delivery_form_province', function (Blueprint $table) {
                $table->string('productos', 255)->default('')->change();
            });
        }

        if (Schema::hasTable('consolidado_delivery_form_lima') && Schema::hasColumn('consolidado_delivery_form_lima', 'productos')) {
            Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
                $table->string('productos')->change();
            });
        }
    }
}
