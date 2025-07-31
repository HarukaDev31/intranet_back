<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixReferenceTablesToBigintUnsigned extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Primero eliminar las foreign keys existentes que dependen de estas columnas
        $this->dropDependentForeignKeys();
        
        // Cambiar el tipo de datos de las columnas id en las tablas de referencia
        try {
            // Cambiar bd_entidades_reguladoras.id a bigint unsigned
            DB::statement('ALTER TABLE bd_entidades_reguladoras MODIFY id bigint unsigned NOT NULL AUTO_INCREMENT');
        } catch (\Exception $e) {
            // Continuar si hay error
        }
        
        try {
            // Cambiar bd_productos.id a bigint unsigned
            DB::statement('ALTER TABLE bd_productos MODIFY id bigint unsigned NOT NULL AUTO_INCREMENT');
        } catch (\Exception $e) {
            // Continuar si hay error
        }
        
        // Recrear las foreign keys que dependen de estas columnas
        $this->recreateDependentForeignKeys();
        
        // Ahora agregar las foreign keys a productos_importados_excel
        try {
            DB::statement('ALTER TABLE productos_importados_excel ADD CONSTRAINT fk_productos_entidad FOREIGN KEY (entidad_id) REFERENCES bd_entidades_reguladoras(id) ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Continuar sin la foreign key si hay error
        }
        
        try {
            DB::statement('ALTER TABLE productos_importados_excel ADD CONSTRAINT fk_productos_tipo_etiquetado FOREIGN KEY (tipo_etiquetado_id) REFERENCES bd_productos(id) ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Continuar sin la foreign key si hay error
        }
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
        
        // Eliminar foreign keys dependientes
        $this->dropDependentForeignKeys();
        
        // Revertir tipos de datos
        try {
            DB::statement('ALTER TABLE bd_entidades_reguladoras MODIFY id int NOT NULL AUTO_INCREMENT');
        } catch (\Exception $e) {
            // Continuar si hay error
        }
        
        try {
            DB::statement('ALTER TABLE bd_productos MODIFY id int NOT NULL AUTO_INCREMENT');
        } catch (\Exception $e) {
            // Continuar si hay error
        }
        
        // Recrear foreign keys dependientes con tipos originales
        $this->recreateDependentForeignKeys();
    }
    
    /**
     * Eliminar foreign keys que dependen de las columnas id
     */
    private function dropDependentForeignKeys()
    {
        // Eliminar foreign keys de bd_productos_regulaciones_permiso
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_permiso DROP FOREIGN KEY fk_permiso_entidad');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        // Eliminar foreign keys de bd_productos_regulaciones_antidumping
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_antidumping DROP FOREIGN KEY fk_antidumping_rubro');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        // Eliminar foreign keys de bd_productos_regulaciones_etiquetado
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_etiquetado DROP FOREIGN KEY fk_etiquetado_rubro');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        // Eliminar foreign keys de bd_productos_regulaciones_documentos_especiales
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_documentos_especiales DROP FOREIGN KEY fk_documentos_especiales_rubro');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
    }
    
    /**
     * Recrear foreign keys que dependen de las columnas id
     */
    private function recreateDependentForeignKeys()
    {
        // Recrear foreign key de bd_productos_regulaciones_permiso
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_permiso ADD CONSTRAINT fk_permiso_entidad FOREIGN KEY (id_entidad_reguladora) REFERENCES bd_entidades_reguladoras(id) ON DELETE CASCADE ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Continuar si hay error
        }
        
        // Recrear foreign key de bd_productos_regulaciones_antidumping
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_antidumping ADD CONSTRAINT fk_antidumping_rubro FOREIGN KEY (id_rubro) REFERENCES bd_productos(id) ON DELETE CASCADE ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Continuar si hay error
        }
        
        // Recrear foreign key de bd_productos_regulaciones_etiquetado
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_etiquetado ADD CONSTRAINT fk_etiquetado_rubro FOREIGN KEY (id_rubro) REFERENCES bd_productos(id) ON DELETE CASCADE ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Continuar si hay error
        }
        
        // Recrear foreign key de bd_productos_regulaciones_documentos_especiales
        try {
            DB::statement('ALTER TABLE bd_productos_regulaciones_documentos_especiales ADD CONSTRAINT fk_documentos_especiales_rubro FOREIGN KEY (id_rubro) REFERENCES bd_productos(id) ON DELETE CASCADE ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Continuar si hay error
        }
    }
}
