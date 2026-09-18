<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddQtyProveedoresToContenedorConsolidadoCotizacion extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'qty_proveedores')) {
                $table->unsignedInteger('qty_proveedores')->nullable()->after('qty_item');
            }
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'qty_proveedores')) {
                $table->dropColumn('qty_proveedores');
            }
        });
    }
}
