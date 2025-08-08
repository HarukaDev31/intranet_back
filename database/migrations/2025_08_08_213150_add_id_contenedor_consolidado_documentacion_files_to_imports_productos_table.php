<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdContenedorConsolidadoDocumentacionFilesToImportsProductosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('imports_productos', function (Blueprint $table) {
            // Verificar si la columna ya existe antes de agregarla
            if (!Schema::hasColumn('imports_productos', 'id_contenedor_consolidado_documentacion_files')) {
                $table->unsignedBigInteger('id_contenedor_consolidado_documentacion_files')->nullable();
                
                // Agregar índice para mejorar performance (con nombre corto)
                $table->index('id_contenedor_consolidado_documentacion_files', 'imports_productos_id_doc_files_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('imports_productos', function (Blueprint $table) {
            // Verificar si la columna existe antes de eliminarla
            if (Schema::hasColumn('imports_productos', 'id_contenedor_consolidado_documentacion_files')) {
                $table->dropIndex('imports_productos_id_doc_files_idx');
                $table->dropColumn('id_contenedor_consolidado_documentacion_files');
            }
        });
    }
}
