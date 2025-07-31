<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToProductosImportadosExcelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('productos_importados_excel', function (Blueprint $table) {
            // Foreign key para entidad_id
            $table->foreign('entidad_id', 'fk_productos_entidad')
                  ->references('id')
                  ->on('bd_entidades_reguladoras')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
            
            // Foreign key para tipo_etiquetado_id
            $table->foreign('tipo_etiquetado_id', 'fk_productos_tipo_etiquetado')
                  ->references('id')
                  ->on('bd_productos')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('productos_importados_excel', function (Blueprint $table) {
            // Eliminar foreign keys
            $table->dropForeign('fk_productos_entidad');
            $table->dropForeign('fk_productos_tipo_etiquetado');
        });
    }
}
