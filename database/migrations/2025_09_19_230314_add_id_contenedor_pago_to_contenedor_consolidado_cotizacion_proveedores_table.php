<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdContenedorPagoToContenedorConsolidadoCotizacionProveedoresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //if arrive_date chins is invalid datetime difernet of null fix it
        Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
            $table->unsignedInteger('id_contenedor_pago')->nullable()->after('id');
            $table->foreign('id_contenedor_pago', 'fk_contenedor_pago_proveedores')->references('id')->on('carga_consolidada_contenedor')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      
    }
}