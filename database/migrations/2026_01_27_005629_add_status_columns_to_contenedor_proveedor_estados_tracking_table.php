<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddStatusColumnsToContenedorProveedorEstadosTrackingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Obtener los valores actuales del enum de la columna estado
        $columnInfo = DB::select("SHOW COLUMNS FROM contenedor_proveedor_estados_tracking LIKE 'estado'");
        
        if (!empty($columnInfo)) {
            $currentEnum = $columnInfo[0]->Type;
            
            // Extraer valores del enum actual
            preg_match("/enum\((.*)\)/", $currentEnum, $matches);
            $currentValues = str_replace("'", "", $matches[1]);
            $currentValuesArray = explode(',', $currentValues);
            
            // Estados a agregar
            $newStates = ['NC', 'C', 'R', 'NS', 'NO LOADED', 'INSPECTION', 'WAIT', 'NP'];
            
            // Agregar estados que no existen
            foreach ($newStates as $state) {
                if (!in_array($state, $currentValuesArray)) {
                    $currentValuesArray[] = $state;
                }
            }
            
            // Crear nuevo enum con todos los valores
            $newEnum = "ENUM('" . implode("', '", $currentValuesArray) . "')";
            
            // Crear columna temporal con el nuevo enum
            DB::statement("ALTER TABLE contenedor_proveedor_estados_tracking ADD COLUMN estado_new {$newEnum} NULL");
            
            // Copiar datos de la columna antigua a la nueva
            DB::statement("UPDATE contenedor_proveedor_estados_tracking SET estado_new = estado");
            
            // Eliminar la columna antigua
            DB::statement("ALTER TABLE contenedor_proveedor_estados_tracking DROP COLUMN estado");
            
            // Renombrar la nueva columna
            DB::statement("ALTER TABLE contenedor_proveedor_estados_tracking CHANGE estado_new estado {$newEnum} NULL");
        } else {
            // Si la columna no existe, crearla con todos los estados
            $allStates = ['NC', 'C', 'R', 'NS', 'NO LOADED', 'INSPECTION', 'WAIT', 'NP'];
            $newEnum = "ENUM('" . implode("', '", $allStates) . "')";
            DB::statement("ALTER TABLE contenedor_proveedor_estados_tracking ADD COLUMN estado {$newEnum} NULL");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Obtener los valores actuales del enum
        $columnInfo = DB::select("SHOW COLUMNS FROM contenedor_proveedor_estados_tracking LIKE 'estado'");
        
        if (!empty($columnInfo)) {
            $currentEnum = $columnInfo[0]->Type;
            
            // Extraer valores del enum actual
            preg_match("/enum\((.*)\)/", $currentEnum, $matches);
            $currentValues = str_replace("'", "", $matches[1]);
            $currentValuesArray = explode(',', $currentValues);
            
            // Remover los estados agregados
            $statesToRemove = ['NC', 'C', 'R', 'NS', 'NO LOADED', 'INSPECTION', 'WAIT', 'NP'];
            $originalValuesArray = array_filter($currentValuesArray, function($value) use ($statesToRemove) {
                return !in_array(trim($value), $statesToRemove);
            });
            
            // Si quedan valores, actualizar el enum
            if (!empty($originalValuesArray)) {
                $originalEnum = "ENUM('" . implode("', '", $originalValuesArray) . "')";
                
                // Crear columna temporal con el enum original
                DB::statement("ALTER TABLE contenedor_proveedor_estados_tracking ADD COLUMN estado_old {$originalEnum} NULL");
                
                // Copiar datos válidos
                DB::statement("UPDATE contenedor_proveedor_estados_tracking SET estado_old = estado WHERE estado NOT IN ('" . implode("', '", $statesToRemove) . "')");
                
                // Eliminar la columna actual
                DB::statement("ALTER TABLE contenedor_proveedor_estados_tracking DROP COLUMN estado");
                
                // Renombrar la columna original
                DB::statement("ALTER TABLE contenedor_proveedor_estados_tracking CHANGE estado_old estado {$originalEnum} NULL");
            }
        }
    }
}
