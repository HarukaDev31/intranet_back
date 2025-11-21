<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsHiddenToConsolidadoDeliveryRangeDateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('consolidado_delivery_range_date', function (Blueprint $table) {
            $table->tinyInteger('is_hidden')->default(0)->after('delivery_count');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('consolidado_delivery_range_date', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
        });
    }
}
