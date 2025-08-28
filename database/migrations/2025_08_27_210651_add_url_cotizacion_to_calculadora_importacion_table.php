<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUrlCotizacionToCalculadoraImportacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->text('url_cotizacion')->nullable()->after('tarifa_total_extra_item');
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
            $table->dropColumn('url_cotizacion');
        });
    }
}
