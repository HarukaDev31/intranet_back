<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsdToContenedorConsolidadoCotizacionTable extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'isd')) {
                $table->decimal('isd', 12, 2)->nullable()->default(0)->after('fob');
            }
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'isd')) {
                $table->dropColumn('isd');
            }
        });
    }
}
