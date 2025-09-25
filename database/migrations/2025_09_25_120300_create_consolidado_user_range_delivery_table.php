<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsolidadoUserRangeDeliveryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('consolidado_user_range_delivery');
        Schema::create('consolidado_user_range_delivery', function (Blueprint $table) {
            $table->id();
            //id_date id_range_date  id_cotizacion timestamp
            $table->unsignedBigInteger('id_date');
            $table->unsignedBigInteger('id_range_date');
            $table->integer('id_cotizacion');
            $table->unsignedBigInteger('id_user');
            $table->foreign('id_date')->references('id')->on('consolidado_delivery_date')->onDelete('cascade');
            $table->foreign('id_range_date')->references('id')->on('consolidado_delivery_range_date')->onDelete('cascade');
            $table->foreign('id_cotizacion')->references('id')->on('contenedor_consolidado_cotizacion')->onDelete('cascade');
            $table->foreign('id_user')->references('id')->on('users')->onDelete('cascade');
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
        Schema::dropIfExists('consolidado_user_range_delivery');
    }
}
