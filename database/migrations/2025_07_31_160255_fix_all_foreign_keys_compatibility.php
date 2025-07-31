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
        DB::statement('ALTER TABLE bd_productos_regulaciones_permiso DROP FOREIGN KEY IF EXISTS fk_permiso_entidad');
        DB::statement('ALTER TABLE bd_productos_regulaciones_antidumping DROP FOREIGN KEY IF EXISTS fk_antidumping_rubro');
        DB::statement('ALTER TABLE bd_productos_regulaciones_etiquetado DROP FOREIGN KEY IF EXISTS fk_etiquetado_rubro');
        DB::statement('ALTER TABLE bd_productos_regulaciones_documentos_especiales DROP FOREIGN KEY IF EXISTS fk_documentos_especiales_rubro');
        
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
        DB::statement('ALTER TABLE productos_importados_excel ADD CONSTRAINT fk_productos_entidad FOREIGN KEY (entidad_id) REFERENCES bd_entidades_reguladoras(id) ON DELETE SET NULL ON UPDATE CASCADE');
        DB::statement('ALTER TABLE productos_importados_excel ADD CONSTRAINT fk_productos_tipo_etiquetado FOREIGN KEY (tipo_etiquetado_id) REFERENCES bd_productos(id) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar foreign keys de productos_importados_excel
        DB::statement('ALTER TABLE productos_importados_excel DROP FOREIGN KEY IF EXISTS fk_productos_entidad');
        DB::statement('ALTER TABLE productos_importados_excel DROP FOREIGN KEY IF EXISTS fk_productos_tipo_etiquetado');
        
        // Eliminar foreign keys recreadas
        DB::statement('ALTER TABLE bd_productos_regulaciones_permiso DROP FOREIGN KEY IF EXISTS fk_permiso_entidad');
        DB::statement('ALTER TABLE bd_productos_regulaciones_antidumping DROP FOREIGN KEY IF EXISTS fk_antidumping_rubro');
        DB::statement('ALTER TABLE bd_productos_regulaciones_etiquetado DROP FOREIGN KEY IF EXISTS fk_etiquetado_rubro');
        DB::statement('ALTER TABLE bd_productos_regulaciones_documentos_especiales DROP FOREIGN KEY IF EXISTS fk_documentos_especiales_rubro');
        
        // Revertir tipos de datos
        DB::statement('ALTER TABLE bd_entidades_reguladoras MODIFY id int NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE bd_productos MODIFY id int NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE bd_productos_regulaciones_permiso MODIFY id_entidad_reguladora int NOT NULL');
        DB::statement('ALTER TABLE bd_productos_regulaciones_antidumping MODIFY id_rubro int NOT NULL');
        DB::statement('ALTER TABLE bd_productos_regulaciones_etiquetado MODIFY id_rubro int NOT NULL');
        DB::statement('ALTER TABLE bd_productos_regulaciones_documentos_especiales MODIFY id_rubro int NOT NULL');
    }
}
