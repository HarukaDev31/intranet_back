<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTcToCalculadoraImportacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->decimal('tc', 10, 4)->nullable()->after('tarifa_descuento');
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
            $table->dropColumn('tc');
        });
    }
}
