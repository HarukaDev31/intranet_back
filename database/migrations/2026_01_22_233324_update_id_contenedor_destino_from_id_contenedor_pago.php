<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateIdContenedorDestinoFromIdContenedorPago extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Actualizar id_contenedor_destino con el valor de id_contenedor_pago
        // para los registros que cumplan las condiciones
        DB::statement("
            UPDATE contenedor_consolidado_cotizacion 
            SET id_contenedor_destino = id_contenedor_pago 
            WHERE id_contenedor_pago IS NOT NULL 
            AND estado_cliente IS NOT NULL 
            AND estado_cotizador = 'CONFIRMADO'
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No hay forma de revertir esta actualización de datos
        // ya que no sabemos qué valores tenía id_contenedor_destino antes
        // Se puede dejar vacío o revertir a id_contenedor si es necesario
        DB::statement("
            UPDATE contenedor_consolidado_cotizacion 
            SET id_contenedor_destino = id_contenedor 
            WHERE id_contenedor_pago IS NOT NULL 
            AND estado_cliente IS NOT NULL 
            AND estado_cotizador = 'CONFIRMADO'
        ");
    }
}
