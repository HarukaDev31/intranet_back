<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeliveryFormRegisteredAtToContenedorConsolidadoCotizacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            // No es nulo cuando el cliente lleno el formulario de entrega/delivery
            $table->date('delivery_form_registered_at')->nullable()->comment('No es nulo cuando el cliente lleno el formulario de entrega/delivery');
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
            $table->dropColumn('delivery_form_registered_at');
        });
    }
}
