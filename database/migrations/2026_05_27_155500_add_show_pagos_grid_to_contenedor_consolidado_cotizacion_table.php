<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShowPagosGridToContenedorConsolidadoCotizacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'show_pagos_grid')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->unsignedTinyInteger('show_pagos_grid')
                    ->default(1)
                    ->after('id_contenedor_pago');
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
        if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'show_pagos_grid')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->dropColumn('show_pagos_grid');
            });
        }
    }
}
