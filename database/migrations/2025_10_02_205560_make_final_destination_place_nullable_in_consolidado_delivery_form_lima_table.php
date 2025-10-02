<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeFinalDestinationPlaceNullableInConsolidadoDeliveryFormLimaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
            $table->string('final_destination_place')->nullable()->change();
            $table->string('final_destination_district')->nullable()->change();
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
            $table->string('final_destination_place')->nullable(false)->change();
        });
    }
}
