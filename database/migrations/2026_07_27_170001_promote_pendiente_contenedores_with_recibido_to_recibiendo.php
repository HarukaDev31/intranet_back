<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige contenedores en PENDIENTE que ya tienen al menos un proveedor en R (Recibido):
 * pasan a RECIBIENDO (estado_china y estado), alineado con promoteContenedorToRecibiendoIfPendiente.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (
            !Schema::hasTable('carga_consolidada_contenedor')
            || !Schema::hasTable('contenedor_consolidado_cotizacion_proveedores')
        ) {
            return;
        }

        DB::statement("
            UPDATE carga_consolidada_contenedor c
            INNER JOIN (
                SELECT DISTINCT p.id_contenedor
                FROM contenedor_consolidado_cotizacion_proveedores p
                WHERE p.estados_proveedor in ('R', 'LOADED','INSPECTION')
                  AND p.id_contenedor IS NOT NULL
            ) r ON r.id_contenedor = c.id
            SET
                c.estado_china = 'RECIBIENDO',
                c.estado = 'RECIBIENDO'
            WHERE c.estado_china = 'PENDIENTE'
        ");
    }

    /**
     * Reverse the migrations.
     *
     * No se revierte: no se puede saber con seguridad cuáles estaban PENDIENTE
     * antes de este backfill.
     */
    public function down(): void
    {
        //
    }
};
