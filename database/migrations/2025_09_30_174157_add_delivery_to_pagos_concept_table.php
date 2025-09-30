<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddDeliveryToPagosConceptTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Insertar el nuevo concepto de pago DELIVERY
        DB::table('cotizacion_coordinacion_pagos_concept')->insert([
            'name' => 'DELIVERY',
           
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar el concepto de pago DELIVERY
        DB::table('contenedor_consolidado_pagos_concept')
            ->where('name', 'DELIVERY')
            ->delete();
    }
}
