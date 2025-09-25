<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsolidadoDeliveryDateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {   
        Schema::dropIfExists('consolidado_delivery_date');

        Schema::create('consolidado_delivery_date', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_contenedor');
            //day month year timestamps
            $table->integer('day');
            $table->integer('month');
            $table->integer('year');
            $table->foreign('id_contenedor')->references('id')->on('carga_consolidada_contenedor')->onDelete('cascade');
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
        Schema::dropIfExists('consolidado_delivery_date');
    }
}
