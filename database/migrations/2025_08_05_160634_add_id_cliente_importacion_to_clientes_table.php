<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdClienteImportacionToClientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedBigInteger('id_cliente_importacion')->nullable();
            
            // Agregar clave foránea hacia la tabla imports_clientes
            $table->foreign('id_cliente_importacion')
                  ->references('id')
                  ->on('imports_clientes')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clientes', function (Blueprint $table) {
            // Primero eliminar la clave foránea
            $table->dropForeign(['id_cliente_importacion']);
            // Luego eliminar la columna
            $table->dropColumn('id_cliente_importacion');
        });
    }
}
