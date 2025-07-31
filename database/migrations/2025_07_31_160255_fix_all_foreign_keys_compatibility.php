<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixAllForeignKeysCompatibility extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Eliminar todas las foreign keys existentes que puedan causar conflictos
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_permiso DROP FOREIGN KEY fk_permiso_entidad');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_antidumping DROP FOREIGN KEY fk_antidumping_rubro');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_etiquetado DROP FOREIGN KEY fk_etiquetado_rubro');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_documentos_especiales DROP FOREIGN KEY fk_documentos_especiales_rubro');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        // 2. Cambiar tipos de datos en las tablas de referencia
        DB::statement('ALTER TABLE bd_entidades_reguladoras MODIFY id int unsigned NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE bd_productos MODIFY id int unsigned NOT NULL AUTO_INCREMENT');
        
        // 3. Cambiar tipos de datos en las columnas que referencian
        DB::statement('ALTER TABLE bd_productos_regulaciones_permiso MODIFY id_entidad_reguladora int unsigned NOT NULL');
        DB::statement('ALTER TABLE bd_productos_regulaciones_antidumping MODIFY id_rubro int unsigned NOT NULL');
        DB::statement('ALTER TABLE bd_productos_regulaciones_etiquetado MODIFY id_rubro int unsigned NOT NULL');
        DB::statement('ALTER TABLE bd_productos_regulaciones_documentos_especiales MODIFY id_rubro int unsigned NOT NULL');
        
        // 4. Recrear las foreign keys
        DB::statement('ALTER TABLE bd_productos_regulaciones_permiso ADD CONSTRAINT fk_permiso_entidad FOREIGN KEY (id_entidad_reguladora) REFERENCES bd_entidades_reguladoras(id) ON DELETE CASCADE ON UPDATE CASCADE');
        DB::statement('ALTER TABLE bd_productos_regulaciones_antidumping ADD CONSTRAINT fk_antidumping_rubro FOREIGN KEY (id_rubro) REFERENCES bd_productos(id) ON DELETE CASCADE ON UPDATE CASCADE');
        DB::statement('ALTER TABLE bd_productos_regulaciones_etiquetado ADD CONSTRAINT fk_etiquetado_rubro FOREIGN KEY (id_rubro) REFERENCES bd_productos(id) ON DELETE CASCADE ON UPDATE CASCADE');
        DB::statement('ALTER TABLE bd_productos_regulaciones_documentos_especiales ADD CONSTRAINT fk_documentos_especiales_rubro FOREIGN KEY (id_rubro) REFERENCES bd_productos(id) ON DELETE CASCADE ON UPDATE CASCADE');
        
        // 5. Agregar foreign keys a productos_importados_excel
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar foreign keys de productos_importados_excel
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
        
        // Eliminar foreign keys recreadas
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_permiso DROP FOREIGN KEY fk_permiso_entidad');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_antidumping DROP FOREIGN KEY fk_antidumping_rubro');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_etiquetado DROP FOREIGN KEY fk_etiquetado_rubro');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_documentos_especiales DROP FOREIGN KEY fk_documentos_especiales_rubro');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        // Revertir tipos de datos
        DB::statement('ALTER TABLE bd_entidades_reguladoras MODIFY id int NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE bd_productos MODIFY id int NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE bd_productos_regulaciones_permiso MODIFY id_entidad_reguladora int NOT NULL');
        DB::statement('ALTER TABLE bd_productos_regulaciones_antidumping MODIFY id_rubro int NOT NULL');
        DB::statement('ALTER TABLE bd_productos_regulaciones_etiquetado MODIFY id_rubro int NOT NULL');
        DB::statement('ALTER TABLE bd_productos_regulaciones_documentos_especiales MODIFY id_rubro int NOT NULL');
    }
}
