<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDomicilioFiscalToConsolidadoDeliveryFormLimaTable extends Migration
{
    public function up()
    {
        Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
            $table->string('domicilio_fiscal')->nullable()->after('voucher_email');
        });
    }

    public function down()
    {
        Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
            $table->dropColumn('domicilio_fiscal');
        });
    }
}
