<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FinalFixForeignKeysCompatibility extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Verificar y corregir tipos de datos en las tablas de referencia
        
        // Corregir bd_entidades_reguladoras.id
        DB::statement('ALTER TABLE bd_entidades_reguladoras MODIFY id int unsigned NOT NULL AUTO_INCREMENT');
        
        // Corregir bd_productos.id
        DB::statement('ALTER TABLE bd_productos MODIFY id int unsigned NOT NULL AUTO_INCREMENT');
        
        // Verificar que las columnas en productos_importados_excel sean unsigned
        DB::statement('ALTER TABLE productos_importados_excel MODIFY entidad_id int unsigned NULL');
        DB::statement('ALTER TABLE productos_importados_excel MODIFY tipo_etiquetado_id int unsigned NULL');
        
        // Ahora intentar agregar las foreign keys
        try {
            DB::statement('ALTER TABLE productos_importados_excel ADD CONSTRAINT fk_productos_entidad FOREIGN KEY (entidad_id) REFERENCES bd_entidades_reguladoras(id) ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Continuar sin la foreign key
        }
        
        try {
            DB::statement('ALTER TABLE productos_importados_excel ADD CONSTRAINT fk_productos_tipo_etiquetado FOREIGN KEY (tipo_etiquetado_id) REFERENCES bd_productos(id) ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Continuar sin la foreign key
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar foreign keys
        try {
            DB::statement('ALTER TABLE productos_importados_excel DROP FOREIGN KEY fk_productos_entidad');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        try {
            DB::statement('ALTER TABLE productos_importados_excel DROP FOREIGN KEY fk_productos_tipo_etiquetado');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        // Revertir tipos de datos
        DB::statement('ALTER TABLE bd_entidades_reguladoras MODIFY id int NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE bd_productos MODIFY id int NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE productos_importados_excel MODIFY entidad_id int NULL');
        DB::statement('ALTER TABLE productos_importados_excel MODIFY tipo_etiquetado_id int NULL');
    }
}
