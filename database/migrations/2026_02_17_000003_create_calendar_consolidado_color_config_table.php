<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalendarConsolidadoColorConfigTable extends Migration
{
    public function up()
    { //drop if exists
        Schema::dropIfExists('calendar_consolidado_color_config');
        Schema::create('calendar_consolidado_color_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('calendar_id');
            $table->unsignedBigInteger('contenedor_id');
            $table->string('color_code', 20);
            $table->timestamps();

            $table->foreign('calendar_id')
                ->references('id')
                ->on('calendars')
                ->onDelete('cascade');

            $table->unique(['calendar_id', 'contenedor_id'], 'cal_consol_color_cal_cont_unique');
            $table->index('contenedor_id', 'cal_consol_color_cont_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('calendar_consolidado_color_config');
    }
}
