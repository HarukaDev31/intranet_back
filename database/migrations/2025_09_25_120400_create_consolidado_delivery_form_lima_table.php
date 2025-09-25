<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsolidadoDeliveryFormLimaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('consolidado_delivery_form_lima');
        Schema::create('consolidado_delivery_form_lima', function (Blueprint $table) {
            $table->id();
            //id_contenedor id_user id_cotizacion  id_range_date pick_name pick_doc import_name productos voucher_doc voucher_doc_type voucher_name voucher_email drver_name driver_doc_type driver_doc driver_license driver_plate final_destination_place final_destination_district
            $table->unsignedInteger('id_contenedor');
            $table->unsignedBigInteger('id_user');
            $table->integer('id_cotizacion');
            $table->unsignedBigInteger('id_range_date');
            $table->string('pick_name');
            $table->string('pick_doc');
            $table->string('import_name');
            $table->string('productos');
            $table->string('voucher_doc');
            $table->enum('voucher_doc_type', ['BOLETA', 'FACTURA']);
            $table->string('voucher_name');
            $table->string('voucher_email');
            $table->string('drver_name');
            $table->enum('driver_doc_type', ['DNI', 'PASAPORTE']);
            $table->string('driver_doc');
            $table->string('driver_license');
            $table->string('driver_plate');
            $table->string('final_destination_place');
            $table->string('final_destination_district');
            $table->foreign('id_contenedor')->references('id')->on('carga_consolidada_contenedor')->onDelete('cascade');
            $table->foreign('id_user')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('id_cotizacion')->references('id')->on('contenedor_consolidado_cotizacion')->onDelete('cascade');
            $table->foreign('id_range_date')->references('id')->on('consolidado_delivery_range_date')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('consolidado_delivery_form_lima');
    }
}
