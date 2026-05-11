<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPickPhoneToConsolidadoDeliveryFormLimaTable extends Migration
{
    public function up()
    {
        Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
            $table->string('pick_phone')->nullable()->after('pick_doc');
        });
    }

    public function down()
    {
        Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
            $table->dropColumn('pick_phone');
        });
    }
}
