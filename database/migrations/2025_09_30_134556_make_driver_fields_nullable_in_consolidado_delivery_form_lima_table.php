<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeDriverFieldsNullableInConsolidadoDeliveryFormLimaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
            // Hacer nullable los campos del conductor
            $table->string('drver_name')->nullable()->change();
            $table->enum('driver_doc_type', ['DNI', 'PASAPORTE'])->nullable()->change();
            $table->string('driver_doc')->nullable()->change();
            $table->string('driver_license')->nullable()->change();
            $table->string('driver_plate')->nullable()->change();
        });
        //DROP COLUMN DRIVER_dOC_TYPE AND  ADD CREATE AGAIN BUT WITH NULLABLE
        Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
            $table->dropColumn('driver_doc_type');
            $table->enum('driver_doc_type', ['DNI', 'PASAPORTE'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
            // Revertir los campos del conductor a no nullable
            $table->string('drver_name')->nullable(false)->change();
            $table->enum('driver_doc_type', ['DNI', 'PASAPORTE'])->nullable(false)->change();
            $table->string('driver_doc')->nullable(false)->change();
            $table->string('driver_license')->nullable(false)->change();
            $table->string('driver_plate')->nullable(false)->change();
        });
    }
}
