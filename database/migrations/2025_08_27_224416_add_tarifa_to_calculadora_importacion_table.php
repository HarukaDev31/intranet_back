<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTarifaToCalculadoraImportacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->decimal('tarifa', 10, 2)->nullable()->after('tarifa_total_extra_item')->comment('Tarifa de importación aplicada');
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
            $table->dropColumn('tarifa');
        });
    }
}
