<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsVerifiedToConsolidadoDeliveryFormsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('consolidado_delivery_form_lima', 'isVerified')) {
            Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
                $table->boolean('isVerified')->default(0);
            });
        }

        if (!Schema::hasColumn('consolidado_delivery_form_province', 'isVerified')) {
            Schema::table('consolidado_delivery_form_province', function (Blueprint $table) {
                $table->boolean('isVerified')->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('consolidado_delivery_form_lima', 'isVerified')) {
            Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
                $table->dropColumn('isVerified');
            });
        }

        if (Schema::hasColumn('consolidado_delivery_form_province', 'isVerified')) {
            Schema::table('consolidado_delivery_form_province', function (Blueprint $table) {
                $table->dropColumn('isVerified');
            });
        }
    }
}
