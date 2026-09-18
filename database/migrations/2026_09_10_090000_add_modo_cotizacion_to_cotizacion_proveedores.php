<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddModoCotizacionToCotizacionProveedores extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'modo_cotizacion')) {
            return;
        }

        Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
            $table->enum('modo_cotizacion', ['itemizado', 'resumen'])
                ->default('itemizado')
                ->after('id_cotizacion');
        });
    }

    public function down()
    {
        if (!Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'modo_cotizacion')) {
            return;
        }

        Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
            $table->dropColumn('modo_cotizacion');
        });
    }
}
