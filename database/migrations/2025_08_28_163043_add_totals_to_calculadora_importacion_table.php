<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTotalsToCalculadoraImportacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->decimal('total_fob', 15, 2)->nullable()->after('tarifa_total_extra_item');
            $table->decimal('total_impuestos', 15, 2)->nullable()->after('total_fob');
            $table->decimal('logistica', 15, 2)->nullable()->after('total_impuestos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->dropColumn(['total_fob', 'total_impuestos', 'logistica']);
        });
    }
}
