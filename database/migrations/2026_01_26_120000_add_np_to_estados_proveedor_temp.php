<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddNpToEstadosProveedorTemp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Crear la columna temporal con el nuevo enum que incluye 'NP' justo después de 'NC'
        DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores ADD COLUMN estados_proveedor_temp ENUM('NC','NP','C','LOADED','R','NO LOADED','INSPECTION','WAIT') NULL");

        // Copiar los valores actuales a la columna temporal
        DB::statement('UPDATE contenedor_consolidado_cotizacion_proveedores SET estados_proveedor_temp = estados_proveedor');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar la columna temporal si existe
        DB::statement('ALTER TABLE contenedor_consolidado_cotizacion_proveedores DROP COLUMN IF EXISTS estados_proveedor_temp');
    }
}
