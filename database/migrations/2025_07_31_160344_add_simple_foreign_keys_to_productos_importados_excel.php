<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddSimpleForeignKeysToProductosImportadosExcel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        try {
            // Agregar foreign key para entidad_id
            DB::statement('ALTER TABLE productos_importados_excel ADD CONSTRAINT fk_productos_entidad FOREIGN KEY (entidad_id) REFERENCES bd_entidades_reguladoras(id) ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Si falla, continuar sin la constraint
        }

        try {
            // Agregar foreign key para tipo_etiquetado_id
            DB::statement('ALTER TABLE productos_importados_excel ADD CONSTRAINT fk_productos_tipo_etiquetado FOREIGN KEY (tipo_etiquetado_id) REFERENCES bd_productos(id) ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Si falla, continuar sin la constraint
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
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
    }
}
