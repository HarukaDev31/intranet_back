<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SwapEstadosProveedorWithTemp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Eliminar la columna original
        DB::statement('ALTER TABLE contenedor_consolidado_cotizacion_proveedores DROP COLUMN estados_proveedor');

        // Renombrar la columna temporal a estados_proveedor (manteniendo el enum que incluye 'NP')
        DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores CHANGE estados_proveedor_temp estados_proveedor ENUM('NC','NP','C','LOADED','R','NO LOADED','INSPECTION','WAIT') NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Volver a crear la columna original sin 'NP'
        DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores ADD COLUMN estados_proveedor_old ENUM('NC','C','LOADED','R','NO LOADED','INSPECTION','WAIT') NULL");

        // Copiar los valores desde la columna (que contiene 'NP') a la columna original reconstruida.
        // Aquellos registros con 'NP' quedarán con 'NP' en la nueva columna; si quieres tratarlos de otra forma,
        // se puede mapear aquí explícitamente antes de copiar.
        DB::statement('UPDATE contenedor_consolidado_cotizacion_proveedores SET estados_proveedor_old = estados_proveedor');

        // Eliminar la columna que actualmente se llama estados_proveedor (que contiene 'NP')
        DB::statement('ALTER TABLE contenedor_consolidado_cotizacion_proveedores DROP COLUMN estados_proveedor');

        // Renombrar la columna reconstruida al nombre original
        DB::statement('ALTER TABLE contenedor_consolidado_cotizacion_proveedores CHANGE estados_proveedor_old estados_proveedor ENUM(\'NC\',\'C\',\'LOADED\',\'R\',\'NO LOADED\',\'INSPECTION\',\'WAIT\') NULL');
    }
}
