<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConfirmationDateToContenedorConsolidadoCotizacionCoordinacionPagosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contenedor_consolidado_cotizacion_coordinacion_pagos', function (Blueprint $table) {
            $table->timestamp('confirmation_date')->nullable()->after('status');
            $table->index('confirmation_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contenedor_consolidado_cotizacion_coordinacion_pagos', function (Blueprint $table) {
            $table->dropIndex(['confirmation_date']);
            $table->dropColumn('confirmation_date');
        });
    }
}
