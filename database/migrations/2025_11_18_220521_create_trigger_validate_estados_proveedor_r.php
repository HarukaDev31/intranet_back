<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateTriggerValidateEstadosProveedorR extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Eliminar el trigger existente si existe
        DB::unprepared('DROP TRIGGER IF EXISTS before_update_validate_estados_proveedor_r');

        // Crear el trigger que valida el cambio a 'R'
        DB::unprepared('
            CREATE TRIGGER before_update_validate_estados_proveedor_r
            BEFORE UPDATE ON contenedor_consolidado_cotizacion_proveedores
            FOR EACH ROW
            BEGIN
                -- Validar si se intenta cambiar estados_proveedor a "R"
                IF NEW.estados_proveedor = "R" AND (OLD.estados_proveedor IS NULL OR OLD.estados_proveedor != "R") THEN
                    -- Verificar si qty_box_china o cbm_total_china son 0 o NULL
                    IF (NEW.qty_box_china IS NULL OR NEW.qty_box_china = 0) AND 
                       (NEW.cbm_total_china IS NULL OR NEW.cbm_total_china = 0) THEN
                        -- Mantener el estado anterior
                        SET NEW.estados_proveedor = OLD.estados_proveedor;
                    END IF;
                END IF;
            END
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar el trigger
        DB::unprepared('DROP TRIGGER IF EXISTS before_update_validate_estados_proveedor_r');
    }
}
