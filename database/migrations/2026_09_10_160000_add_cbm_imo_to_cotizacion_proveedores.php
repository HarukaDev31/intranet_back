<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCbmImoToCotizacionProveedores extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'cbm_imo')) {
            $after = Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'maxcbm')
                ? 'maxcbm'
                : 'cbm_total';
            Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) use ($after) {
                $table->decimal('cbm_imo', 10, 4)->nullable()->default(0)->after($after);
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'cbm_imo')) {
            Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
                $table->dropColumn('cbm_imo');
            });
        }
    }
}
