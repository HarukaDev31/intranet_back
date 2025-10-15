<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddWaitStatusToEstadosProveedorEnum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Paso 1: Obtener los valores actuales del enum
        $columnInfo = DB::select("SHOW COLUMNS FROM contenedor_consolidado_cotizacion_proveedores LIKE 'estados_proveedor'");
        $currentEnum = $columnInfo[0]->Type;
        
        // Extraer valores del enum actual
        preg_match("/enum\((.*)\)/", $currentEnum, $matches);
        $currentValues = str_replace("'", "", $matches[1]);
        $currentValuesArray = explode(',', $currentValues);
        
        // Agregar 'WAIT' si no existe
        if (!in_array('WAIT', $currentValuesArray)) {
            $currentValuesArray[] = 'WAIT';
        }
        
        // Crear nuevo enum con todos los valores
        $newEnum = "ENUM('" . implode("', '", $currentValuesArray) . "') DEFAULT 'WAIT'";
        
        // Paso 2: Crear nueva columna con el enum actualizado
        DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores ADD COLUMN estados_proveedor_new {$newEnum}");
        
        // Paso 3: Copiar datos de la columna antigua a la nueva
        DB::statement("UPDATE contenedor_consolidado_cotizacion_proveedores SET estados_proveedor_new = estados_proveedor");
        
        // Paso 4: Eliminar la columna antigua
        DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores DROP COLUMN estados_proveedor");
        
        // Paso 5: Renombrar la nueva columna
        DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores CHANGE estados_proveedor_new estados_proveedor {$newEnum}");
        
        // Paso 6: Actualizar los estados según la lógica especificada
        $this->updateEstadosProveedor();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Obtener los valores actuales del enum (sin WAIT)
        $columnInfo = DB::select("SHOW COLUMNS FROM contenedor_consolidado_cotizacion_proveedores LIKE 'estados_proveedor'");
        $currentEnum = $columnInfo[0]->Type;
        
        // Extraer valores del enum actual
        preg_match("/enum\((.*)\)/", $currentEnum, $matches);
        $currentValues = str_replace("'", "", $matches[1]);
        $currentValuesArray = explode(',', $currentValues);
        
        // Remover 'WAIT' si existe
        $currentValuesArray = array_filter($currentValuesArray, function($value) {
            return trim($value) !== 'WAIT';
        });
        
        // Revertir a enum original
        $originalEnum = "ENUM('" . implode("', '", $currentValuesArray) . "') DEFAULT 'R'";
        DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores MODIFY COLUMN estados_proveedor {$originalEnum}");
    }

    /**
     * Actualizar los estados de los proveedores según la lógica especificada
     */
    private function updateEstadosProveedor()
    {
        // Obtener todos los registros con estado 'R'
        $proveedores = DB::table('contenedor_consolidado_cotizacion_proveedores')
            ->where('estados_proveedor', 'R')
            ->get();

        foreach ($proveedores as $proveedor) {
            $nuevoEstado = $this->determinarNuevoEstado($proveedor);
            
            if ($nuevoEstado !== 'R') {
                DB::table('contenedor_consolidado_cotizacion_proveedores')
                    ->where('id', $proveedor->id)
                    ->update(['estados_proveedor' => $nuevoEstado]);
            }
        }
    }

    /**
     * Determinar el nuevo estado según la lógica especificada
     */
    private function determinarNuevoEstado($proveedor)
    {
        $qtyBoxChina = (int)($proveedor->qty_box_china ?? 0);
        $cbmTotalChina = (float)($proveedor->cbm_total_china ?? 0);
        $supplier = !empty($proveedor->supplier);
        $supplierPhone = !empty($proveedor->supplier_phone);
        
        // Verificar arrive_date_china de forma segura (manejar fechas inválidas)
        $arriveDateChina = false;
        if (!empty($proveedor->arrive_date_china) && 
            $proveedor->arrive_date_china !== '0000-00-00' && 
            $proveedor->arrive_date_china !== '0000-00-00 00:00:00' &&
            $proveedor->arrive_date_china !== null) {
            try {
                $date = new DateTime($proveedor->arrive_date_china);
                $arriveDateChina = true;
            } catch (Exception $e) {
                $arriveDateChina = false;
            }
        }

        // Si tiene qty_box_china o cbm_total_china, se queda en R
        if ($qtyBoxChina > 0 || $cbmTotalChina > 0) {
            return 'R';
        }

        // Si no tiene qty_box_china ni cbm_total_china (o están en 0)
        // Verificar si tiene supplier y supplier_phone
        if ($supplier && $supplierPhone) {
            // Si tiene arrive_date_china, poner en C
            if ($arriveDateChina) {
                return 'C';
            }
            // Si no tiene arrive_date_china, poner en NC
            return 'NC';
        }

        // Si no tiene supplier ni supplier_phone, poner en WAIT
        return 'WAIT';
    }
}