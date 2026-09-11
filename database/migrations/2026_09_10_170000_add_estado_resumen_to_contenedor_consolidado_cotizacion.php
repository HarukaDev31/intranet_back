<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado propio del flujo "resumen" (orgs sin calculadora).
 * No se tocan los enums compartidos estado / estado_cotizador.
 */
class AddEstadoResumenToContenedorConsolidadoCotizacion extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'estado_resumen')) {
            return;
        }

        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $after = Schema::hasColumn('contenedor_consolidado_cotizacion', 'estado_cotizador')
                ? 'estado_cotizador'
                : 'estado';
            $table->enum('estado_resumen', ['COTIZADO', 'CONFIRMADO'])
                ->nullable()
                ->after($after);
        });
    }

    public function down()
    {
        if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'estado_resumen')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->dropColumn('estado_resumen');
            });
        }
    }
}
