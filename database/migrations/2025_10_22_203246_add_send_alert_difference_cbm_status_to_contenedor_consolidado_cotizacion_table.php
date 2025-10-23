<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSendAlertDifferenceCbmStatusToContenedorConsolidadoCotizacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->enum('send_alert_difference_cbm_status', ['PENDING', 'SENDED'])
                  ->default('PENDING')
                  ->after('uuid')
                  ->comment('Estado del envío de alerta de diferencia CBM');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->dropColumn('send_alert_difference_cbm_status');
        });
    }
}
