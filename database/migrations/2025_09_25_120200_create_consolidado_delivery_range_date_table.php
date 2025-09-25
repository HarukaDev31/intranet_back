<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsolidadoDeliveryRangeDateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //drop if exists
        Schema::create('consolidado_delivery_range_date', function (Blueprint $table) {
            $table->id();
            //id id_date start_time  end_time delivery_count
            $table->unsignedBigInteger('id_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('delivery_count');
            $table->foreign('id_date')->references('id')->on('consolidado_delivery_date')->onDelete('cascade');
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
        Schema::dropIfExists('consolidado_delivery_range_date');
    }
}
