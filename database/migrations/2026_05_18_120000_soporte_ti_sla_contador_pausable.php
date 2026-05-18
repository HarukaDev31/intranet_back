<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SoporteTiSlaContadorPausable extends Migration
{
    public function up()
    {
        Schema::table('soporte_ti_solicitudes', function (Blueprint $table) {
            $table->unsignedInteger('sla_segundos_acumulados')->default(0)->after('horas_transcurridas');
            $table->timestamp('sla_reanudado_en')->nullable()->after('sla_segundos_acumulados');
        });
    }

    public function down()
    {
        Schema::table('soporte_ti_solicitudes', function (Blueprint $table) {
            $table->dropColumn(array('sla_segundos_acumulados', 'sla_reanudado_en'));
        });
    }
}
