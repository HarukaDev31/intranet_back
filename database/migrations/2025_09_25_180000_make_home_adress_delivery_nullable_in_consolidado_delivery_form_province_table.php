<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeHomeAdressDeliveryNullableInConsolidadoDeliveryFormProvinceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('consolidado_delivery_form_province', function (Blueprint $table) {
            $table->string('home_adress_delivery')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('consolidado_delivery_form_province', function (Blueprint $table) {
            $table->string('home_adress_delivery')->nullable(false)->change();
        });
    }
}
