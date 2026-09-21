<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos de coordinación insertados con DB::table() no copiaban organizacion_id.
 * Se rellena desde la cotización o, si falta, desde el contenedor.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $pagos = 'contenedor_consolidado_cotizacion_coordinacion_pagos';
        $cotizacion = 'contenedor_consolidado_cotizacion';
        $contenedor = 'carga_consolidada_contenedor';

        if (
            !Schema::hasTable($pagos)
            || !Schema::hasTable($cotizacion)
            || !Schema::hasColumn($pagos, 'organizacion_id')
        ) {
            return;
        }

        DB::statement("
            UPDATE {$pagos} p
            INNER JOIN {$cotizacion} c ON c.id = p.id_cotizacion
            LEFT JOIN {$contenedor} cont ON cont.id = COALESCE(p.id_contenedor, c.id_contenedor)
            SET p.organizacion_id = COALESCE(c.organizacion_id, cont.organizacion_id)
            WHERE (p.organizacion_id IS NULL OR p.organizacion_id = 0)
              AND COALESCE(c.organizacion_id, cont.organizacion_id) IS NOT NULL
              AND COALESCE(c.organizacion_id, cont.organizacion_id) > 0
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
