<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeIdRangeDateNullableInConsolidadoDeliveryFormLimaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
            // Eliminar la foreign key primero
            $table->dropForeign(['id_range_date']);
        });

        Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
            // Hacer nullable la columna
            $table->unsignedBigInteger('id_range_date')->nullable()->change();
        });

        Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
            // Recrear la foreign key (ahora permite null)
            $table->foreign('id_range_date')
                  ->references('id')
                  ->on('consolidado_delivery_range_date')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
            // Eliminar la foreign key
            $table->dropForeign(['id_range_date']);
        });

        Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
            // Hacer la columna NOT NULL nuevamente
            $table->unsignedBigInteger('id_range_date')->nullable(false)->change();
        });

        Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
            // Recrear la foreign key
            $table->foreign('id_range_date')
                  ->references('id')
                  ->on('consolidado_delivery_range_date')
                  ->onDelete('cascade');
        });
    }
}
