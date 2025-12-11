<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class MakeImporterNmaeNullableInConsolidadoDeliveryFormProvinceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('consolidado_delivery_form_province', function (Blueprint $table) {
            $table->string('importer_nmae')->nullable()->change();
            $table->string('voucher_doc')->nullable()->change();
            $table->string('voucher_name')->nullable()->change();
            $table->string('agency_address_final_delivery')->nullable()->change();
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
            $table->string('importer_nmae')->nullable(false)->change();
            $table->string('voucher_doc')->nullable(false)->change();
            $table->string('voucher_name')->nullable(false)->change();
            $table->string('agency_address_final_delivery')->nullable(false)->change();
        });
    }
}
