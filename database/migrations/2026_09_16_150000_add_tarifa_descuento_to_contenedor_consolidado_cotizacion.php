<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTarifaDescuentoToContenedorConsolidadoCotizacion extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'tarifa_descuento')) {
                $table->decimal('tarifa_descuento', 12, 2)->nullable()->default(0)->after('tarifa');
            }
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'tarifa_descuento')) {
                $table->dropColumn('tarifa_descuento');
            }
        });
    }
}
