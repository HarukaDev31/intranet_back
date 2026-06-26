<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRecargosDescuentoToContenedorConsolidadoCotizacionTable extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'recargos')) {
                $table->decimal('recargos', 10, 4)
                    ->nullable()
                    ->after('recargos_descuentos_final');
            }

            if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'descuento')) {
                $table->decimal('descuento', 10, 4)
                    ->nullable()
                    ->after('recargos');
            }
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'descuento')) {
                $table->dropColumn('descuento');
            }

            if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'recargos')) {
                $table->dropColumn('recargos');
            }
        });
    }
}
