<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeyIdGrupoToUsuarioTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('usuario', function (Blueprint $table) {
            // Agregar foreign key constraint para ID_Grupo
            $table->foreign('ID_Grupo')
                  ->references('ID_Grupo')
                  ->on('grupo')
                  ->onDelete('restrict')
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
        Schema::table('usuario', function (Blueprint $table) {
            // Eliminar foreign key constraint
            $table->dropForeign(['ID_Grupo']);
        });
    }
}
