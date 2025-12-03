<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddMaquinariaToTipoProductoEnumInContenedorConsolidadoCotizacionProveedoresItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Paso 1: Obtener los valores actuales del enum
        $columnInfo = DB::select("SHOW COLUMNS FROM contenedor_consolidado_cotizacion_proveedores_items LIKE 'tipo_producto'");
        $currentEnum = $columnInfo[0]->Type;
        
        // Extraer valores del enum actual
        preg_match("/enum\((.*)\)/", $currentEnum, $matches);
        $currentValues = str_replace("'", "", $matches[1]);
        $currentValuesArray = array_map('trim', explode(',', $currentValues));
        
        // Agregar 'MAQUINARIA' si no existe
        if (!in_array('MAQUINARIA', $currentValuesArray)) {
            $currentValuesArray[] = 'MAQUINARIA';
        }
        
        // Crear nuevo enum con todos los valores (mantener el default 'GENERAL')
        $newEnum = "ENUM('" . implode("', '", $currentValuesArray) . "') DEFAULT 'GENERAL'";
        
        // Paso 2: Crear nueva columna con el enum actualizado
        DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores_items ADD COLUMN tipo_producto_new {$newEnum}");
        
        // Paso 3: Copiar datos de la columna antigua a la nueva
        DB::statement("UPDATE contenedor_consolidado_cotizacion_proveedores_items SET tipo_producto_new = tipo_producto");
        
        // Paso 4: Eliminar la columna antigua
        DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores_items DROP COLUMN tipo_producto");
        
        // Paso 5: Renombrar la nueva columna
        DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores_items CHANGE tipo_producto_new tipo_producto {$newEnum}");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Obtener los valores actuales del enum (sin MAQUINARIA)
        $columnInfo = DB::select("SHOW COLUMNS FROM contenedor_consolidado_cotizacion_proveedores_items LIKE 'tipo_producto'");
        $currentEnum = $columnInfo[0]->Type;
        
        // Extraer valores del enum actual
        preg_match("/enum\((.*)\)/", $currentEnum, $matches);
        $currentValues = str_replace("'", "", $matches[1]);
        $currentValuesArray = array_map('trim', explode(',', $currentValues));
        
        // Remover 'MAQUINARIA' si existe
        $currentValuesArray = array_filter($currentValuesArray, function($value) {
            return trim($value) !== 'MAQUINARIA';
        });
        
        // Revertir a enum original (valores originales sin MAQUINARIA)
        $originalEnum = "ENUM('" . implode("', '", $currentValuesArray) . "') DEFAULT 'GENERAL'";
        
        // Usar el mismo proceso para revertir
        DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores_items ADD COLUMN tipo_producto_old {$originalEnum}");
        DB::statement("UPDATE contenedor_consolidado_cotizacion_proveedores_items SET tipo_producto_old = IF(tipo_producto = 'MAQUINARIA', 'GENERAL', tipo_producto)");
        DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores_items DROP COLUMN tipo_producto");
        DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores_items CHANGE tipo_producto_old tipo_producto {$originalEnum}");
    }
}
