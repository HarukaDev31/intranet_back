<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeAgencyNameAndAgencyRucNullableInConsolidadoDeliveryFormProvinceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('consolidado_delivery_form_province', function (Blueprint $table) {
            //
            $table->string('agency_name')->nullable()->change();
            $table->string('agency_ruc')->nullable()->change();
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
            //
        });
    }
}
