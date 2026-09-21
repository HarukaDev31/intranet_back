<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Excel Conf. (Coord 2) quedó en Solicitado aunque el cliente ya había llenado el form.
 * El guardado público no avanzaba desde Solicitado. Pasa a Entregado si hay ítems del formulario.
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
        $proveedores = 'contenedor_consolidado_cotizacion_proveedores';
        $itemsExcel = 'contenedor_consolidado_cotizacion_proveedores_items_excel_conf';

        if (
            !Schema::hasTable($proveedores)
            || !Schema::hasTable($itemsExcel)
            || !Schema::hasColumn($proveedores, 'excel_conf_status')
        ) {
            return;
        }

        DB::statement("
            UPDATE {$proveedores} p
            INNER JOIN (
                SELECT DISTINCT id_proveedor
                FROM {$itemsExcel}
                WHERE id_proveedor IS NOT NULL
            ) e ON e.id_proveedor = p.id
            SET p.excel_conf_status = 'Entregado'
            WHERE p.excel_conf_status = 'Solicitado'
        ");
    }

    /**
     * Reverse the migrations.
     *
     * No se revierte: no se puede distinguir de un Entregado posterior al llenado.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
