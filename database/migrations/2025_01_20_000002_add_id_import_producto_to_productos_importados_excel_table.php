<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdImportProductoToProductosImportadosExcelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('productos_importados_excel', function (Blueprint $table) {
            $table->unsignedBigInteger('id_import_producto')->nullable()->after('idContenedor');
            
            // Índice
            $table->index('id_import_producto');
            
            // Clave foránea
            $table->foreign('id_import_producto')->references('id')->on('imports_productos')->onDelete('cascade');
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
            $table->dropForeign(['id_import_producto']);
            $table->dropIndex(['id_import_producto']);
            $table->dropColumn('id_import_producto');
        });
    }
}
