<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTipoServicioToContenedorConsolidadoCotizacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'tipo_servicio')) {
                $table->enum('tipo_servicio', ['DELIVERY', 'MONTACARGA'])->default('DELIVERY')->nullable()->after('total_pago_delivery')->comment('Tipo de servicio: DELIVERY o MONTACARGA');
            }
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
           
            //create enum column tipo_servicio
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'tipo_servicio')) {
                $table->enum('tipo_servicio', ['DELIVERY', 'MONTACARGA'])->nullable()->after('total_pago_delivery')->comment('Tipo de servicio: DELIVERY o MONTACARGA');
            }
        });
    }
}
